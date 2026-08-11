<?php

namespace App\Http\Controllers;

use App\Prompts\WandaPrompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class TelegramController extends Controller
{
    private $token;
    private $wandaUrl;
    private $wandaToken;
    private $geminiKey;

    public function __construct()
    {
        $this->token      = config('app.telegram_token');
        $this->wandaUrl   = config('app.wanda_api_url');
        $this->wandaToken = config('app.wanda_token');
        $this->geminiKey  = config('app.gemini_api_key');
    }

    // ─────────────────────────────────────────────────────
    // WEBHOOK PRINCIPAL
    // Punto de entrada de todos los mensajes de Telegram.
    // ─────────────────────────────────────────────────────

    public function webhook(Request $request)
    {
        $message = $request->input('message');
        if (!$message) return response()->json(['ok' => true]);

        $chatId = $message['chat']['id'];

        // Verificar que el usuario esté en la lista de permitidos
        $allowedUsers = array_filter(explode(',', env('ALLOWED_USERS', '')));
        if (!empty($allowedUsers) && !in_array((string)$chatId, $allowedUsers)) {
            $this->sendMessage($chatId, "🚫 No tienes acceso a Wanda.");
            return response()->json(['ok' => true]);
        }

        $texto = trim($message['text'] ?? '');

        // "cancelar" termina cualquier flujo activo
        if (strtolower($texto) === 'cancelar') {
            $this->borrarSesion($chatId);
            $this->sendMessage($chatId, "❌ Operación cancelada.");
            return response()->json(['ok' => true]);
        }

        // Si hay un cuestionario en curso, continuar con él
        $sesion = $this->getSesion($chatId);
        if ($sesion && $sesion['flujo'] === 'nuevo_movimiento') {
            $this->procesarMovimiento($chatId, $texto, $sesion);
            return response()->json(['ok' => true]);
        }

        // Pedirle a Gemini que interprete el mensaje
        $resultado = $this->preguntarGemini($texto);
        $accion    = $resultado['accion'];

        switch ($accion) {
            case 'resumen':
            case 'resumen_anual':
                $this->consultarFinanciero($chatId, $texto);
                break;

            case 'nuevo_movimiento':
                $this->setSesion($chatId, ['flujo' => 'nuevo_movimiento', 'paso' => 'tipo']);
                $this->sendMessage($chatId, "📝 *Nuevo movimiento*\n\n¿Qué tipo es?\n\nEscribe *ingreso*, *gasto* o *pago_tarjeta*\n\n_(Escribe *cancelar* en cualquier momento para salir)_");
                break;

            case 'inventario':
                if (empty($resultado['busqueda']) && empty($resultado['categoria'])) {
                    $this->sendMessage($chatId, "🔍 ¿Qué componente quieres buscar?");
                } else {
                    $this->responderInventario($chatId, $resultado);
                }
                break;

            case 'modificar_stock':
                if (empty($resultado['num_componente']) || $resultado['cantidad_stock'] === null) {
                    $this->sendMessage($chatId, "⚠️ Necesito el número de componente y la cantidad. Ejemplo: *agrega 5 al IRFB4227PBF*");
                } else {
                    $this->modificarStock($chatId, $resultado['num_componente'], $resultado['cantidad_stock']);
                }
                break;

            default:
                $this->sendMessage($chatId, "👋 Hola, soy Wanda\n\nPuedo ayudarte con:\n\n📊 *Ver resumen* — ingresos y gastos del mes o del año\n📅 *Cortes mensuales* — si un mes ya está cerrado y sus cifras\n💰 *Pendientes de cobro* — cuánto deben los clientes y el balance neto\n📝 *Nuevo movimiento* — registrar un ingreso o gasto\n🔍 *Inventario* — buscar stock de componentes\n📦 *Modificar stock* — agregar o quitar unidades\n\n_(Escribe *cancelar* para cancelar cualquier operación)_");
        }

        return response()->json(['ok' => true]);
    }

    // ─────────────────────────────────────────────────────
    // GEMINI
    // Interpreta el mensaje del usuario y decide qué hacer.
    // ─────────────────────────────────────────────────────

    private function preguntarGemini($mensaje)
    {
        $prompt = WandaPrompt::clasificar($mensaje, now()->format('d/m/Y'));

        try {
            $response = Http::timeout(10)->retry(3, 500)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$this->geminiKey}",
                ['contents' => [['parts' => [['text' => $prompt]]]]]
            );

            $texto = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '{"accion":"desconocido"}';
            $texto = trim(str_replace(['```json', '```'], '', $texto));
            $data  = json_decode($texto, true);

            $resultado = [
                'accion'       => $data['accion']      ?? 'desconocido',
                'mes'          => $data['mes']          ?? null,
                'anio'         => $data['anio']         ?? null,
                'mes_relativo' => $data['mes_relativo'] ?? false,
                'busqueda'     => $data['busqueda']     ?? null,
                'num_componente'  => $data['num_componente']  ?? null,
                'cantidad_stock'  => $data['cantidad_stock']  ?? null,
                'categoria'       => $data['categoria']       ?? null,
                'subcategoria'        => $data['subcategoria']        ?? null,
                'capacitancia_valor'  => $data['capacitancia_valor']  ?? null,
                'capacitancia_unidad' => $data['capacitancia_unidad'] ?? null,
                'voltaje'             => $data['voltaje']             ?? null,
                'resistencia_valor'   => $data['resistencia_valor']   ?? null,
                'resistencia_unidad'  => $data['resistencia_unidad']  ?? null,
                'potencia_watts'      => $data['potencia_watts']      ?? null,
            ];

            //logger('Gemini resultado: ' . json_encode($resultado));

            return $resultado;

        } catch (\Exception $e) {
            logger('Gemini error: ' . $e->getMessage());
            return ['accion' => 'desconocido', 'mes' => null, 'anio' => null, 'mes_relativo' => false, 'busqueda' => null];
        }
    }

    private function generarComentario($data)
    {
        $prompt = WandaPrompt::comentarioResumen($data);

        try {
            $response = Http::timeout(10)->retry(3, 500)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$this->geminiKey}",
                ['contents' => [['parts' => [['text' => $prompt]]]]]
            );

            return trim($response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '');

        } catch (\Exception $e) {
            return '';
        }
    }

    // ─────────────────────────────────────────────────────
    // CONSULTA FINANCIERA CON FUNCTION CALLING
    // Gemini decide qué datos necesita y Wanda los consulta.
    // ─────────────────────────────────────────────────────

    private function consultarFinanciero($chatId, $texto)
    {
        $mensajes = [
            ['role' => 'user', 'parts' => [['text' => $texto]]]
        ];

        try {
            // Máximo 5 iteraciones para evitar loops infinitos
            for ($i = 0; $i < 5; $i++) {

                $response = Http::timeout(30)->retry(3, 500)->post(
                    "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$this->geminiKey}",
                    [
                        'system_instruction' => ['parts' => [['text' => WandaPrompt::sistemaFinanciero(now()->format('d/m/Y'))]]],
                        'contents'           => $mensajes,
                        'tools'              => [['function_declarations' => WandaPrompt::herramientas()]],
                    ]
                );

                $candidate = $response->json()['candidates'][0]['content'] ?? null;
                if (!$candidate) break;

                $mensajes[] = $candidate;

                $parts        = $candidate['parts'] ?? [];
                $textoPart    = collect($parts)->first(fn($p) => isset($p['text']));
                $funcionParts = collect($parts)->filter(fn($p) => isset($p['functionCall']));

                // Si Gemini responde con texto es la respuesta final
                if ($textoPart) {
                    $this->sendMessage($chatId, $textoPart['text']);
                    return;
                }

                // Si hay function calls, ejecutarlas todas
                if ($funcionParts->isNotEmpty()) {
                    $resultados = [];

                    foreach ($funcionParts as $part) {
                        $nombreFuncion = $part['functionCall']['name'];
                        $args          = $part['functionCall']['args'];
                        $resultado     = $this->ejecutarHerramienta($nombreFuncion, $args);

                        $resultados[] = [
                            'functionResponse' => [
                                'name'     => $nombreFuncion,
                                'response' => $resultado,
                            ]
                        ];
                    }

                    // Devolver todos los resultados a Gemini en un solo mensaje
                    $mensajes[] = [
                        'role'  => 'user',
                        'parts' => $resultados,
                    ];
                } else {
                    break;
                }
            }
        } catch (\Exception $e) {
            logger('consultarFinanciero error: ' . $e->getMessage());
            $this->sendMessage($chatId, "⚠️ No pude conectar con el servidor. Intenta de nuevo.");
            return;
        }

        $this->sendMessage($chatId, "⚠️ No pude procesar tu consulta. Intenta de nuevo.");
    }

    private function ejecutarHerramienta($nombre, $args)
    {
        try {
            if ($nombre === 'obtener_resumen_mensual') {
                $response = Http::timeout(10)->withHeaders([
                    'X-Wanda-Token' => $this->wandaToken,
                ])->get("{$this->wandaUrl}/api/wanda/resumen", [
                    'mes'  => $args['mes'],
                    'anio' => $args['anio'],
                ]);
                return $response->json();
            }

            if ($nombre === 'obtener_resumen_anual') {
                $params = ['anio' => $args['anio']];
                if (!empty($args['categoria'])) $params['categoria'] = $args['categoria'];

                $response = Http::timeout(10)->withHeaders([
                    'X-Wanda-Token' => $this->wandaToken,
                ])->get("{$this->wandaUrl}/api/wanda/resumen-anual", $params);
                return $response->json();
            }

            if ($nombre === 'obtener_cortes_mensuales') {
                $params = [];
                if (!empty($args['anio'])) $params['anio'] = $args['anio'];
                if (!empty($args['mes']))  $params['mes']  = $args['mes'];

                $response = Http::timeout(10)->withHeaders([
                    'X-Wanda-Token' => $this->wandaToken,
                ])->get("{$this->wandaUrl}/api/wanda/cortes", $params);
                return $response->json();
            }

            if ($nombre === 'obtener_pendientes_cobro') {
                $response = Http::timeout(10)->withHeaders([
                    'X-Wanda-Token' => $this->wandaToken,
                ])->get("{$this->wandaUrl}/api/wanda/pendientes-cobro");
                return $response->json();
            }

            return ['error' => 'Herramienta no encontrada'];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────
    // INVENTARIO
    // Busca componentes por nombre o número de parte.
    // ─────────────────────────────────────────────────────

    private function responderInventario($chatId, array $filtros)
    {
        try {
            $params = array_filter([
                'q'                   => $filtros['busqueda']            ?? null,
                'categoria'           => $filtros['categoria']           ?? null,
                'subcategoria'        => $filtros['subcategoria']        ?? null,
                'voltaje'             => $filtros['voltaje']             ?? null,
                'capacitancia_valor'  => $filtros['capacitancia_valor']  ?? null,
                'capacitancia_unidad' => $filtros['capacitancia_unidad'] ?? null,
                'resistencia_valor'   => $filtros['resistencia_valor']   ?? null,
                'resistencia_unidad'  => $filtros['resistencia_unidad']  ?? null,
                'potencia_watts'      => $filtros['potencia_watts']      ?? null,
            ], fn ($v) => $v !== null && $v !== '');

            //logger('Buscando inventario: ' . json_encode($params));

            $response = Http::timeout(10)->withHeaders([
                'X-Wanda-Token' => $this->wandaToken,
            ])->get("{$this->wandaUrl}/api/wanda/inventario", $params);

            //logger('Inventario status: ' . $response->status());
            //logger('Inventario body: ' . $response->body());

            if (!$response->successful()) {
                $this->sendMessage($chatId, "⚠️ " . $this->extraerMensajeError($response, 'No pude conectar con la base de datos. Intenta de nuevo.'));
                return;
            }

            $data = $response->json();

            $etiqueta = $filtros['busqueda'] ?? trim(($filtros['categoria'] ?? '') . ' ' . ($filtros['subcategoria'] ?? ''));

            if ($data['total'] === 0) {
                $this->sendMessage($chatId, "🔍 No encontré ningún componente para \"$etiqueta\".");
                return;
            }

            $texto = "🔍 *Resultados para \"$etiqueta\"* ({$data['total']} encontrados)\n\n";

            foreach ($data['resultados'] as $item) {
                $specs = $this->formatearSpecs($item);

                $texto .= "📦 *{$item['componente']}*\n";
                if (!empty($item['categoria'])) {
                    $texto .= "Categoría: {$item['categoria']}" . (!empty($item['subcategoria']) ? " ({$item['subcategoria']})" : '') . "\n";
                }
                if ($specs) {
                    $texto .= implode(' · ', $specs) . "\n";
                }
                $texto .= "Num: `{$item['num_componente']}`\n";
                $texto .= "Stock: {$item['stock']} | Caja: {$item['caja_locacion']}\n";
                $texto .= "Proveedor: {$item['proveedor']}\n";
                $texto .= "P/U: {$item['precio']} (Puede variar)\n\n";
            }

            $this->sendMessage($chatId, $texto);

        } catch (\Exception $e) {
            logger('Inventario error: ' . $e->getMessage());
            $this->sendMessage($chatId, "⚠️ No pude conectar con la base de datos. Intenta de nuevo.");
        }
    }

    private function formatearSpecs(array $item): array
    {
        $fmt = fn ($v) => rtrim(rtrim((string) $v, '0'), '.');
        $simbolos = ['Ohm' => 'Ω', 'kOhm' => 'kΩ', 'MOhm' => 'MΩ'];

        $specs = [];

        if (!empty($item['capacitancia_valor'])) {
            $specs[] = $fmt($item['capacitancia_valor']) . ($item['capacitancia_unidad'] ?? '');
        }
        if (!empty($item['voltaje'])) {
            $specs[] = $fmt($item['voltaje']) . 'V';
        }
        if (!empty($item['resistencia_valor'])) {
            $specs[] = $fmt($item['resistencia_valor']) . ($simbolos[$item['resistencia_unidad']] ?? $item['resistencia_unidad'] ?? '');
        }
        if (!empty($item['potencia_watts'])) {
            $specs[] = $fmt($item['potencia_watts']) . 'W';
        }

        return $specs;
    }

    // ─────────────────────────────────────────────────────
    // NUEVO MOVIMIENTO
    // Cuestionario paso a paso para registrar un movimiento.
    // ─────────────────────────────────────────────────────

    private function procesarMovimiento($chatId, $texto, $sesion)
    {
        $omitir = strtolower($texto) === 'omitir';

        switch ($sesion['paso']) {
            case 'tipo':
                $tipo = strtolower($texto);
                if (!in_array($tipo, ['ingreso', 'gasto', 'pago_tarjeta'])) {
                    $this->sendMessage($chatId, "⚠️ Escribe *ingreso*, *gasto* o *pago_tarjeta*.");
                    return;
                }
                $sesion['tipo'] = $tipo;
                $sesion['paso'] = 'cantidad';
                $this->setSesion($chatId, $sesion);
                $this->sendMessage($chatId, "💰 ¿Cuánto? (solo el número, ejemplo: 350)");
                break;

            case 'cantidad':
                if (!is_numeric($texto) || $texto <= 0) {
                    $this->sendMessage($chatId, "⚠️ Escribe solo el monto, ejemplo: *350*");
                    return;
                }
                $sesion['cantidad'] = $texto;
                $sesion['paso']     = 'descripcion';
                $this->setSesion($chatId, $sesion);
                $this->sendMessage($chatId, "📄 ¿Descripción? (o escribe *omitir*)");
                break;

            case 'descripcion':
                $sesion['descripcion'] = $omitir ? null : $texto;

                // pago_tarjeta no tiene categoría, subcategoría ni proyecto
                if ($sesion['tipo'] === 'pago_tarjeta') {
                    $sesion['paso'] = 'fecha';
                    $this->setSesion($chatId, $sesion);
                    $this->sendMessage($chatId, "📅 ¿Fecha? Escribe *hoy* o una fecha (ejemplo: 2026-05-15)");
                } else {
                    $sesion['paso'] = 'categoria';
                    $this->setSesion($chatId, $sesion);
                    $this->sendMessage($chatId, "🏷️ ¿Categoría? (o escribe *omitir*)");
                }
                break;

            case 'categoria':
                $sesion['categoria'] = $omitir ? null : $texto;
                $sesion['paso']      = 'subcategoria';
                $this->setSesion($chatId, $sesion);
                $this->sendMessage($chatId, "🏷️ ¿Subcategoría? (o escribe *omitir*)");
                break;

            case 'subcategoria':
                $sesion['subcategoria'] = $omitir ? null : $texto;
                $sesion['paso']         = 'proyecto';
                $this->setSesion($chatId, $sesion);
                $this->sendMessage($chatId, "📁 ¿Proyecto? (o escribe *omitir*)");
                break;

            case 'proyecto':
                $sesion['proyecto'] = $omitir ? null : $texto;
                $sesion['paso']     = 'fecha';
                $this->setSesion($chatId, $sesion);
                $this->sendMessage($chatId, "📅 ¿Fecha? Escribe *hoy* o una fecha (ejemplo: 2026-05-15)");
                break;

            case 'fecha':
                if (strtolower($texto) === 'hoy') {
                    $sesion['fecha'] = now()->toDateString();
                } else {
                    if (!strtotime($texto)) {
                        $this->sendMessage($chatId, "⚠️ Fecha inválida. Escribe *hoy* o una fecha como *2026-05-15*");
                        return;
                    }
                    $sesion['fecha'] = date('Y-m-d', strtotime($texto));
                }
                $this->guardarMovimiento($chatId, $sesion);
                break;
        }
    }

    private function guardarMovimiento($chatId, $sesion)
    {
        try {
            $response = Http::timeout(10)->withHeaders([
                'X-Wanda-Token' => $this->wandaToken,
            ])->post("{$this->wandaUrl}/api/wanda/movimiento", [
                'tipo'         => $sesion['tipo'],
                'cantidad'     => $sesion['cantidad'],
                'fecha'        => $sesion['fecha'],
                'descripcion'  => $sesion['descripcion']  ?? null,
                'categoria'    => $sesion['categoria']    ?? null,
                'subcategoria' => $sesion['subcategoria'] ?? null,
                'proyecto'     => $sesion['proyecto']     ?? null,
            ]);

            if ($response->successful()) {
                $tipo     = ucfirst($sesion['tipo']);
                $cantidad = number_format($sesion['cantidad'], 2);
                $this->sendMessage($chatId, "✅ *$tipo de \$$cantidad registrado correctamente*\n📅 Fecha: {$sesion['fecha']}");
            } else {
                $this->sendMessage($chatId, "⚠️ " . $this->extraerMensajeError($response, 'No se pudo guardar el movimiento. Intenta de nuevo.'));
            }
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "⚠️ No se pudo conectar con el servidor. Intenta de nuevo.");
        }

        $this->borrarSesion($chatId);
    }

    // ─────────────────────────────────────────────────────
    // MODIFICAR STOCK
    // Agrega o quita unidades de un componente del inventario.
    // ─────────────────────────────────────────────────────

    private function modificarStock($chatId, $numComponente, $cantidad)
    {
        try {
            $response = Http::timeout(10)->withHeaders([
                'X-Wanda-Token' => $this->wandaToken,
            ])->post("{$this->wandaUrl}/api/wanda/inventario/stock", [
                'num_componente' => $numComponente,
                'cantidad'       => $cantidad,
            ]);

            if (!$response->successful()) {
                $data = $response->json();

                if (isset($data['error']) && $data['error'] === 'Stock insuficiente') {
                    $this->sendMessage($chatId, "⚠️ Stock insuficiente. Stock actual: {$data['stock_actual']}");
                } elseif (isset($data['error']) && $data['error'] === 'Componente no encontrado') {
                    $this->sendMessage($chatId, "⚠️ No encontré el componente *$numComponente*.");
                } else {
                    $this->sendMessage($chatId, "⚠️ " . $this->extraerMensajeError($response, 'No se pudo modificar el stock. Intenta de nuevo.'));
                }
                return;
            }

            $data      = $response->json();
            $accion    = $cantidad > 0 ? "agregaron $cantidad" : "quitaron " . abs($cantidad);

            $this->sendMessage($chatId, "✅ Se $accion unidades de *{$data['componente']}*\n📦 Stock anterior: {$data['stock_antes']}\n📦 Stock actual: {$data['stock_ahora']}");

        } catch (\Exception $e) {
            logger('modificarStock error: ' . $e->getMessage());
            $this->sendMessage($chatId, "⚠️ No se pudo conectar con el servidor. Intenta de nuevo.");
        }
    }

    // ─────────────────────────────────────────────────────
    // SESIONES
    // Guarda el estado del cuestionario en un archivo JSON.
    // ─────────────────────────────────────────────────────

    private function getSesion($chatId)
    {
        $data = json_decode(Storage::get('sesiones.json') ?? '{}', true);
        return $data[$chatId] ?? null;
    }

    private function setSesion($chatId, $sesion)
    {
        $data = json_decode(Storage::get('sesiones.json') ?? '{}', true);
        $data[$chatId] = $sesion;
        Storage::put('sesiones.json', json_encode($data));
    }

    private function borrarSesion($chatId)
    {
        $data = json_decode(Storage::get('sesiones.json') ?? '{}', true);
        unset($data[$chatId]);
        Storage::put('sesiones.json', json_encode($data));
    }

    // ─────────────────────────────────────────────────────
    // ERRORES
    // Saca el motivo real de una respuesta fallida de la API de control
    // general (guard personalizado con 'error', o validación estándar de
    // Laravel con 'errors'), para no responderle siempre lo mismo al usuario.
    // ─────────────────────────────────────────────────────

    private function extraerMensajeError($response, string $default): string
    {
        $data = $response->json();

        if (!empty($data['error'])) {
            return $data['error'];
        }

        if (!empty($data['errors'])) {
            $primero = collect($data['errors'])->flatten()->first();
            if ($primero) return $primero;
        }

        return $default;
    }

    // ─────────────────────────────────────────────────────
    // TELEGRAM
    // Envía un mensaje al usuario.
    // ─────────────────────────────────────────────────────

    private function sendMessage($chatId, $text)
    {
        Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ]);
    }
}