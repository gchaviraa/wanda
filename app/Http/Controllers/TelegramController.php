<?php

namespace App\Http\Controllers;

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

    // ── Sesiones ──────────────────────────────────────────
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

    // ── Gemini ────────────────────────────────────────────
    private function preguntarGemini($mensaje)
    {
        $prompt = <<<EOT
    Eres el asistente Wanda. Analiza el siguiente mensaje del usuario y responde ÚNICAMENTE con una de estas acciones en JSON:

    {"accion": "resumen"} — si el usuario quiere ver ingresos, gastos, balance o resumen del mes
    {"accion": "nuevo_movimiento"} — si el usuario quiere registrar, agregar o crear un ingreso o gasto
    {"accion": "desconocido"} — si no entiendes qué quiere el usuario

    Mensaje del usuario: "$mensaje"

    Responde SOLO con el JSON, sin explicaciones.
    EOT;

        try {
            $response = Http::timeout(10)->post(
                "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key={$this->geminiKey}",
                [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ]
                ]
            );

            $texto = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '{"accion":"desconocido"}';
            $texto = trim(str_replace(['```json', '```'], '', $texto));
            $data  = json_decode($texto, true);

            return $data['accion'] ?? 'desconocido';

        } catch (\Exception $e) {
            logger('Gemini error: ' . $e->getMessage());
            return 'desconocido';
        }
    }

    // ── Webhook principal ─────────────────────────────────
    public function webhook(Request $request)
    {
        $message = $request->input('message');
        if (!$message) return response()->json(['ok' => true]);

        $chatId = $message['chat']['id'];

        logger('ChatId recibido: ' . $chatId);
        logger('Allowed users: ' . config('app.allowed_users'));

        // Verificar usuario permitido
        $allowedUsers = array_filter(explode(',', config('app.allowed_users')));
        if (!empty($allowedUsers) && !in_array((string)$chatId, $allowedUsers)) {
            $this->sendMessage($chatId, "No tienes acceso a Wanda.");
            return response()->json(['ok' => true]);
        }
        
        $texto  = trim($message['text'] ?? '');

        // Cancelar en cualquier momento
        if (strtolower($texto) === 'cancelar') {
            $this->borrarSesion($chatId);
            $this->sendMessage($chatId, "❌ Operación cancelada.");
            return response()->json(['ok' => true]);
        }

        // Ver si hay una sesión activa
        $sesion = $this->getSesion($chatId);

        if ($sesion && $sesion['flujo'] === 'nuevo_movimiento') {
            $this->procesarMovimiento($chatId, $texto, $sesion);
            return response()->json(['ok' => true]);
        }

        // Preguntarle a Gemini qué quiere hacer el usuario
        $accion = $this->preguntarGemini($texto);

        switch ($accion) {
            case 'resumen':
                $this->responderResumen($chatId);
                break;

            case 'nuevo_movimiento':
                $this->setSesion($chatId, ['flujo' => 'nuevo_movimiento', 'paso' => 'tipo']);
                $this->sendMessage($chatId, "📝 *Nuevo movimiento*\n\n¿Es un ingreso o gasto?\n\nEscribe *ingreso* o *gasto*\n\n_(Escribe *cancelar* en cualquier momento para salir)_");
                break;

            default:
                $this->sendMessage($chatId, "Hola, soy Wanda 👋\n\nPuedo ayudarte con:\n\n📊 *Ver resumen* — ingresos y gastos del mes\n📝 *Nuevo movimiento* — registrar un ingreso o gasto\n\n_(Escribe *cancelar* para cancelar cualquier operación)_");
        }

        return response()->json(['ok' => true]);
    }

    // ── Flujo nuevo movimiento ────────────────────────────
    private function procesarMovimiento($chatId, $texto, $sesion)
    {
        $omitir = strtolower($texto) === 'omitir';

        switch ($sesion['paso']) {
            case 'tipo':
                $tipo = strtolower($texto);
                if (!in_array($tipo, ['ingreso', 'gasto'])) {
                    $this->sendMessage($chatId, "⚠️ Escribe *ingreso* o *gasto*.");
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
                $sesion['paso']        = 'categoria';
                $this->setSesion($chatId, $sesion);
                $this->sendMessage($chatId, "🏷️ ¿Categoría? (o escribe *omitir*)");
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

    // ── Guardar movimiento via API ────────────────────────
    private function guardarMovimiento($chatId, $sesion)
    {
        try {
            $response = Http::timeout(10)->withHeaders([
                'X-Wanda-Token' => $this->wandaToken,
            ])->post("{$this->wandaUrl}/api/wanda/movimiento", [
                'tipo'         => $sesion['tipo'],
                'cantidad'     => $sesion['cantidad'],
                'fecha'        => $sesion['fecha'],
                'descripcion'  => $sesion['descripcion'] ?? null,
                'categoria'    => $sesion['categoria'] ?? null,
                'subcategoria' => $sesion['subcategoria'] ?? null,
                'proyecto'     => $sesion['proyecto'] ?? null,
            ]);

            if ($response->successful()) {
                $tipo     = ucfirst($sesion['tipo']);
                $cantidad = number_format($sesion['cantidad'], 2);
                $fecha    = $sesion['fecha'];
                $this->sendMessage($chatId, "✅ *$tipo de \$$cantidad registrado correctamente*\n📅 Fecha: $fecha");
            } else {
                $this->sendMessage($chatId, "⚠️ No se pudo guardar el movimiento. Intenta de nuevo.");
            }
        } catch (\Exception $e) {
            $this->sendMessage($chatId, "⚠️ No se pudo conectar con el servidor. Intenta de nuevo.");
        }

        $this->borrarSesion($chatId);
    }

    // ── Resumen mensual ───────────────────────────────────
    private function responderResumen($chatId)
    {
        try {
            $response = Http::timeout(10)->withHeaders([
                'X-Wanda-Token' => $this->wandaToken,
            ])->get("{$this->wandaUrl}/api/wanda/resumen", [
                'mes'  => now()->month,
                'anio' => now()->year,
            ]);

            if (!$response->successful()) {
                $this->sendMessage($chatId, "⚠️ No pude conectar con la base de datos. Verifica que el servidor esté activo e intenta de nuevo.");
                return;
            }

            $data     = $response->json();
            $ingresos = number_format($data['ingresos'], 2);
            $gastos   = number_format($data['gastos'], 2);
            $credito  = number_format($data['credito'], 2);
            $balance  = number_format($data['balance'], 2);

            $this->sendMessage($chatId, "📊 *Resumen de {$data['periodo']}*\n\n✅ Ingresos: \$$ingresos\n❌ Gastos: \$$gastos\n💳 Crédito: \$$credito\n💰 Balance: \$$balance");

        } catch (\Exception $e) {
            $this->sendMessage($chatId, "⚠️ No pude conectar con la base de datos. Verifica que el servidor esté activo e intenta de nuevo.");
        }
    }

    // ── Enviar mensaje ────────────────────────────────────
    private function sendMessage($chatId, $text)
    {
        Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'Markdown',
        ]);
    }
}