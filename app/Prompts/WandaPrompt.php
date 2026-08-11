<?php

namespace App\Prompts;

class WandaPrompt
{
    /**
     * Prompt principal que Gemini usa para interpretar el mensaje del usuario.
     * Devuelve un JSON con la acción a tomar y los parámetros necesarios.
     */
    public static function clasificar(string $mensaje, string $fechaActual): string
    {
        return <<<EOT
    Eres el asistente Wanda. Analiza el siguiente mensaje del usuario y responde ÚNICAMENTE con un JSON con esta estructura:

    {
    "accion": "resumen" | "resumen_anual" | "nuevo_movimiento" | "inventario" | "modificar_stock" | "desconocido",
    "mes": null | número del mes (1-12),
    "anio": null | año (ejemplo: 2026),
    "mes_relativo": false | true,
    "busqueda": null | texto a buscar en inventario,
    "num_componente": null | número de componente exacto,
    "cantidad_stock": null | número entero (positivo para agregar, negativo para quitar),
    "categoria": null | nombre de la categoría,
    "subcategoria": null | subcategoría del componente (solo para accion=inventario),
    "capacitancia_valor": null | número (solo para accion=inventario, capacitores),
    "capacitancia_unidad": null | "pF" | "nF" | "uF" (solo para accion=inventario, capacitores),
    "voltaje": null | número en volts (solo para accion=inventario, capacitores),
    "resistencia_valor": null | número (solo para accion=inventario, resistencias),
    "resistencia_unidad": null | "Ohm" | "kOhm" | "MOhm" (solo para accion=inventario, resistencias),
    "potencia_watts": null | número en watts (solo para accion=inventario, resistencias)
    }

    Reglas:
    - "accion" es "resumen" si el usuario quiere cualquier información financiera: mensual, anual, corridas, comparaciones, si un mes está cerrado (Cortes mensuales), cuánto deben los clientes (saldo pendiente de cobro) o el balance neto, etc.
    - "accion" es "nuevo_movimiento" si el usuario quiere registrar, agregar o crear un ingreso o gasto
    - "accion" es "inventario" si el usuario pregunta por stock, componentes, partes o inventario
    - "accion" es "modificar_stock" si el usuario quiere agregar o quitar unidades de un componente
    - "accion" es "desconocido" si no entiendes qué quiere
    - "mes" y "anio" solo si el usuario menciona un mes o año específico, si no ponlos en null
    - "mes_relativo" es true si el usuario dice "mes pasado", "el mes anterior" o similar
    - "busqueda" debe contener solo el término técnico a buscar, sin palabras descriptivas — úsalo cuando el usuario da un texto libre (nombre de parte, número de componente) en vez de (o además de) specs estructuradas
    - "num_componente" es el número exacto del componente cuando el usuario quiere modificar stock
    - "cantidad_stock" es positivo para agregar, negativo para quitar
    - "categoria" tiene DOS significados distintos según la acción — nunca se usan juntos:
        - Si accion="resumen_anual": es una categoría FINANCIERA. Válidas: Reparacion, Venta, Miscelaneo, Vending Machine, Electro, EPTech, Tax Acreditable, Cargos Financieros, Gastos de Nomina
        - Si accion="inventario": es un TIPO DE COMPONENTE. Válidas: Capacitor, Resistencia, Transistor, IC, Diodo, Conector, Inductor, Otro
    - Para accion="inventario", cuando el usuario mencione specs de un capacitor o resistencia, extráelas en los campos correspondientes en vez de (o además de) meterlas en "busqueda":
        - "microfaradios"/"uF"/"µF" → capacitancia_unidad="uF"; "nanofaradios"/"nF" → "nF"; "picofaradios"/"pF" → "pF"
        - un número seguido de "V" o "volts" en contexto de capacitor → voltaje
        - "ohms"/"Ω" → resistencia_unidad="Ohm"; "kilohms"/"kΩ"/"K" → "kOhm"; "megaohms"/"MΩ"/"M" → "MOhm"
        - un número seguido de "W"/"watts" en contexto de resistencia → potencia_watts
    - La fecha actual es: $fechaActual

    Ejemplos:
    - "cómo vamos este mes" → {"accion":"resumen","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":null,"subcategoria":null,"capacitancia_valor":null,"capacitancia_unidad":null,"voltaje":null,"resistencia_valor":null,"resistencia_unidad":null,"potencia_watts":null}
    - "resumen de abril" → {"accion":"resumen","mes":4,"anio":2026,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":null,"subcategoria":null,"capacitancia_valor":null,"capacitancia_unidad":null,"voltaje":null,"resistencia_valor":null,"resistencia_unidad":null,"potencia_watts":null}
    - "resumen del año" → {"accion":"resumen_anual","mes":null,"anio":2026,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":null,"subcategoria":null,"capacitancia_valor":null,"capacitancia_unidad":null,"voltaje":null,"resistencia_valor":null,"resistencia_unidad":null,"potencia_watts":null}
    - "resumen anual de reparaciones" → {"accion":"resumen_anual","mes":null,"anio":2026,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":"Reparacion","subcategoria":null,"capacitancia_valor":null,"capacitancia_unidad":null,"voltaje":null,"resistencia_valor":null,"resistencia_unidad":null,"potencia_watts":null}
    - "cuántos 10uF 100V tenemos" → {"accion":"inventario","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":"Capacitor","subcategoria":null,"capacitancia_valor":10,"capacitancia_unidad":"uF","voltaje":100,"resistencia_valor":null,"resistencia_unidad":null,"potencia_watts":null}
    - "tienes resistencias de 10K de 1/4 de watt" → {"accion":"inventario","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":"Resistencia","subcategoria":null,"capacitancia_valor":null,"capacitancia_unidad":null,"voltaje":null,"resistencia_valor":10,"resistencia_unidad":"kOhm","potencia_watts":0.25}
    - "cuántos capacitores electrolíticos tenemos" → {"accion":"inventario","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":"Capacitor","subcategoria":"Electrolítico","capacitancia_valor":null,"capacitancia_unidad":null,"voltaje":null,"resistencia_valor":null,"resistencia_unidad":null,"potencia_watts":null}
    - "busca el IRFB4227PBF" → {"accion":"inventario","mes":null,"anio":null,"mes_relativo":false,"busqueda":"IRFB4227PBF","num_componente":null,"cantidad_stock":null,"categoria":null,"subcategoria":null,"capacitancia_valor":null,"capacitancia_unidad":null,"voltaje":null,"resistencia_valor":null,"resistencia_unidad":null,"potencia_watts":null}
    - "agrega 5 al IRFB4227PBF" → {"accion":"modificar_stock","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":"IRFB4227PBF","cantidad_stock":5,"categoria":null,"subcategoria":null,"capacitancia_valor":null,"capacitancia_unidad":null,"voltaje":null,"resistencia_valor":null,"resistencia_unidad":null,"potencia_watts":null}
    - "quita 2 del UKL2A100MPD1AA" → {"accion":"modificar_stock","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":"UKL2A100MPD1AA","cantidad_stock":-2,"categoria":null,"subcategoria":null,"capacitancia_valor":null,"capacitancia_unidad":null,"voltaje":null,"resistencia_valor":null,"resistencia_unidad":null,"potencia_watts":null}
    - "quiero registrar un gasto" → {"accion":"nuevo_movimiento","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":null,"subcategoria":null,"capacitancia_valor":null,"capacitancia_unidad":null,"voltaje":null,"resistencia_valor":null,"resistencia_unidad":null,"potencia_watts":null}
    - "¿ya cerraste junio?" → {"accion":"resumen","mes":6,"anio":2026,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":null,"subcategoria":null,"capacitancia_valor":null,"capacitancia_unidad":null,"voltaje":null,"resistencia_valor":null,"resistencia_unidad":null,"potencia_watts":null}
    - "cuánto nos deben" → {"accion":"resumen","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":null,"subcategoria":null,"capacitancia_valor":null,"capacitancia_unidad":null,"voltaje":null,"resistencia_valor":null,"resistencia_unidad":null,"potencia_watts":null}

    Mensaje del usuario: "$mensaje"

    Responde SOLO con el JSON, sin explicaciones.
    EOT;
    }

    /**
     * Prompt para generar un comentario breve sobre el resumen financiero.
     */
    public static function comentarioResumen(array $data): string
    {
        return "Eres Wanda, un asistente de negocios. Con base en estos datos financieros del período {$data['periodo']}, escribe UN comentario corto (máximo 2 oraciones) en español sobre cómo va el negocio. Sé directo y útil. No uses emojis ni formato markdown.

        Datos:
        - Ingresos: \${$data['ingresos']}
        - Gastos: \${$data['gastos']}
        - Saldo tarjeta pendiente: \${$data['saldo_tarjeta']}
        - Balance: \${$data['balance']}";
    }

    public static function herramientas(): array
    {
        return [
            [
                'name'        => 'obtener_resumen_mensual',
                'description' => 'Obtiene el resumen financiero de un mes específico: ingresos, gastos, saldo de tarjeta y balance.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'mes'  => ['type' => 'integer', 'description' => 'Número del mes (1-12)'],
                        'anio' => ['type' => 'integer', 'description' => 'Año (ejemplo: 2026)'],
                    ],
                    'required' => ['mes', 'anio'],
                ],
            ],
            [
                'name'        => 'obtener_resumen_anual',
                'description' => 'Obtiene el resumen financiero de un año completo: ingresos, gastos, saldo de tarjeta y balance. Opcionalmente se puede filtrar por categoría.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'anio'      => ['type' => 'integer', 'description' => 'Año (ejemplo: 2026)'],
                        'categoria' => ['type' => 'string',  'description' => 'Categoría opcional: Reparacion, Venta, Miscelaneo, Vending Machine, Electro, EPTech, Tax Acreditable, Cargos Financieros, Gastos de Nomina'],
                    ],
                    'required' => ['anio'],
                ],
            ],
            [
                'name'        => 'obtener_cortes_mensuales',
                'description' => 'Consulta los Cortes mensuales (meses ya cerrados) y sus cifras congeladas: ingresos, gastos, balance y balance neto. Si se dan mes y año, indica si ese mes específico está cerrado y sus cifras; sin parámetros, devuelve la lista de todos los cierres (opcionalmente filtrada por año).',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'anio' => ['type' => 'integer', 'description' => 'Año a consultar (opcional)'],
                        'mes'  => ['type' => 'integer', 'description' => 'Mes a consultar, 1-12 (opcional, se usa junto con anio)'],
                    ],
                ],
            ],
            [
                'name'        => 'obtener_pendientes_cobro',
                'description' => 'Obtiene cuánto dinero deben los clientes ahora mismo (reparaciones sin pagar + otras cuentas por cobrar) y el balance neto actual del negocio (banco menos deuda de tarjeta más lo pendiente de cobro).',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => (object) [],
                ],
            ],
        ];
    }

    public static function sistemaFinanciero(string $fechaActual): string
    {
        return "Eres Wanda, un asistente financiero para un negocio de reparación electrónica. 
    La fecha actual es $fechaActual.
    Tienes acceso a herramientas para consultar datos financieros reales, incluyendo si un mes ya está cerrado (Cortes mensuales) y cuánto deben los clientes ahora mismo (saldo pendiente de cobro y balance neto).
    Cuando el usuario pida información financiera, usa las herramientas disponibles para obtener los datos y luego responde en español de forma clara y concisa.
    No inventes números — siempre consulta las herramientas primero.
    Si el usuario pide una corrida de varios meses, consulta cada mes por separado.
    Si el usuario pregunta si un mes está cerrado o pide las cifras de un cierre, usa obtener_cortes_mensuales.
    Si el usuario pregunta cuánto le deben, saldo pendiente de cobro, o balance neto, usa obtener_pendientes_cobro.
    Siempre muestra los montos con el símbolo de dólar y dos decimales, ejemplo: \$1,234.56
    Responde sin markdown ni formato especial, solo texto plano con saltos de línea.";
    }
}