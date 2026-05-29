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

        // Determinar mes y año (actual por defecto, o el que extrajo Gemini)
        $mes  = $resultado['mes'] ?? now()->month;
        $anio = $resultado['anio'] ?? now()->year;

        // Si dijo "mes pasado", calcularlo en PHP
        if ($resultado['mes_relativo'] ?? false) {
            $mes  = now()->subMonth()->month;
            $anio = now()->subMonth()->year;
        }

        switch ($accion) {
            case 'resumen':
                $this->responderResumen($chatId, $mes, $anio);
                break;

            case 'nuevo_movimiento':
                $this->setSesion($chatId, ['flujo' => 'nuevo_movimiento', 'paso' => 'tipo']);
                $this->sendMessage($chatId, "📝 *Nuevo movimiento*\n\n¿Qué tipo es?\n\nEscribe *ingreso*, *gasto* o *pago_tarjeta*\n\n_(Escribe *cancelar* en cualquier momento para salir)_");
                break;

            case 'inventario':
                if (empty($resultado['busqueda'])) {
                    $this->sendMessage($chatId, "🔍 ¿Qué componente quieres buscar?");
                } else {
                    $this->responderInventario($chatId, $resultado['busqueda']);
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
                $this->sendMessage($chatId, "👋 Hola, soy Wanda\n\nPuedo ayudarte con:\n\n📊 *Ver resumen* — ingresos y gastos del mes\n📝 *Nuevo movimiento* — registrar un ingreso o gasto\n🔍 *Inventario* — buscar stock de componentes\n\n_(Escribe *cancelar* para cancelar cualquier operación)_");
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
            $response = Http::timeout(10)->post(
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
            $response = Http::timeout(10)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$this->geminiKey}",
                ['contents' => [['parts' => [['text' => $prompt]]]]]
            );

            return trim($response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '');

        } catch (\Exception $e) {
            return '';
        }
    }

    // ─────────────────────────────────────────────────────
    // RESUMEN MENSUAL
    // Consulta la API de eptech y responde con los totales.
    // ─────────────────────────────────────────────────────

    private function responderResumen($chatId, $mes = null, $anio = null)
    {
        $mes  = $mes  ?? now()->month;
        $anio = $anio ?? now()->year;

        try {
            $response = Http::timeout(10)->withHeaders([
                'X-Wanda-Token' => $this->wandaToken,
            ])->get("{$this->wandaUrl}/api/wanda/resumen", [
                'mes'  => $mes,
                'anio' => $anio,
            ]);

            if (!$response->successful()) {
                $this->sendMessage($chatId, "⚠️ No pude conectar con la base de datos. Verifica que el servidor esté activo e intenta de nuevo.");
                return;
            }

            $data         = $response->json();
            $ingresos     = number_format($data['ingresos'], 2);
            $gastos       = number_format($data['gastos'], 2);
            $saldoTarjeta = number_format($data['saldo_tarjeta'], 2);
            $balance      = number_format($data['balance'], 2);

            $comentario = $this->generarComentario($data);

            $this->sendMessage($chatId, "$comentario\n\n📊 *Resumen de {$data['periodo']}*\n\n✅ Ingresos: \$$ingresos\n❌ Gastos: \$$gastos\n💳 Saldo tarjeta: \$$saldoTarjeta\n💰 Balance: \$$balance");

        } catch (\Exception $e) {
            $this->sendMessage($chatId, "⚠️ No pude conectar con la base de datos. Verifica que el servidor esté activo e intenta de nuevo.");
        }
    }

    // ─────────────────────────────────────────────────────
    // INVENTARIO
    // Busca componentes por nombre o número de parte.
    // ─────────────────────────────────────────────────────

    private function responderInventario($chatId, $busqueda)
    {
        try {
            //logger('Buscando inventario: ' . $busqueda);

            $response = Http::timeout(10)->withHeaders([
                'X-Wanda-Token' => $this->wandaToken,
            ])->get("{$this->wandaUrl}/api/wanda/inventario", [
                'q' => $busqueda,
            ]);

            //logger('Inventario status: ' . $response->status());
            //logger('Inventario body: ' . $response->body());

            if (!$response->successful()) {
                $this->sendMessage($chatId, "⚠️ No pude conectar con la base de datos. Intenta de nuevo.");
                return;
            }

            $data = $response->json();

            if ($data['total'] === 0) {
                $this->sendMessage($chatId, "🔍 No encontré ningún componente para \"$busqueda\".");
                return;
            }

            $texto = "🔍 *Resultados para \"$busqueda\"* ({$data['total']} encontrados)\n\n";

            foreach ($data['resultados'] as $item) {
                $texto .= "📦 *{$item['componente']}*\n";
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
                $this->sendMessage($chatId, "⚠️ No se pudo guardar el movimiento. Intenta de nuevo.");
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
                    $this->sendMessage($chatId, "⚠️ No se pudo modificar el stock. Intenta de nuevo.");
                }
                return;
            }

            $data      = $response->json();
            $accion    = $cantidad > 0 ? "agregaron $cantidad" : "quitaron " . abs($cantidad);

            $this->sendMessage($chatId, "✅ Se $accion unidades de *{$data['componente']}*\n📦 Stock anterior: {$data['stock_antes']}\n📦 Stock actual: {$data['stock_ahora']}");

        } catch (\Exception $e) {
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