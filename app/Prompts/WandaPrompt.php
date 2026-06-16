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
    "categoria": null | nombre de la categoría
    }

    Reglas:
    - "accion" es "resumen" si el usuario quiere cualquier información financiera: mensual, anual, corridas, comparaciones, etc.
    - "accion" es "nuevo_movimiento" si el usuario quiere registrar, agregar o crear un ingreso o gasto
    - "accion" es "inventario" si el usuario pregunta por stock, componentes, partes o inventario
    - "accion" es "modificar_stock" si el usuario quiere agregar o quitar unidades de un componente
    - "accion" es "desconocido" si no entiendes qué quiere
    - "mes" y "anio" solo si el usuario menciona un mes o año específico, si no ponlos en null
    - "mes_relativo" es true si el usuario dice "mes pasado", "el mes anterior" o similar
    - "busqueda" debe contener solo el término técnico a buscar, sin palabras descriptivas
    - "num_componente" es el número exacto del componente cuando el usuario quiere modificar stock
    - "cantidad_stock" es positivo para agregar, negativo para quitar
    - "categoria" es la categoría específica si el usuario la menciona, si no null
    - Las categorías válidas son: Reparacion, Miscelaneo, Vending Machine, Electro, EPTech
    - La fecha actual es: $fechaActual

    Ejemplos:
    - "cómo vamos este mes" → {"accion":"resumen","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":null}
    - "resumen de abril" → {"accion":"resumen","mes":4,"anio":2026,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":null}
    - "resumen del año" → {"accion":"resumen_anual","mes":null,"anio":2026,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":null}
    - "resumen anual 2025" → {"accion":"resumen_anual","mes":null,"anio":2025,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":null}
    - "resumen anual de reparaciones" → {"accion":"resumen_anual","mes":null,"anio":2026,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":"Reparacion"}
    - "cómo van las reparaciones este año" → {"accion":"resumen_anual","mes":null,"anio":2026,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":"Reparacion"}
    - "cuántos 10uF 100V tenemos" → {"accion":"inventario","mes":null,"anio":null,"mes_relativo":false,"busqueda":"10uF 100V","num_componente":null,"cantidad_stock":null,"categoria":null}
    - "agrega 5 al IRFB4227PBF" → {"accion":"modificar_stock","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":"IRFB4227PBF","cantidad_stock":5,"categoria":null}
    - "quita 2 del UKL2A100MPD1AA" → {"accion":"modificar_stock","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":"UKL2A100MPD1AA","cantidad_stock":-2,"categoria":null}
    - "quiero registrar un gasto" → {"accion":"nuevo_movimiento","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null,"categoria":null}

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
                        'categoria' => ['type' => 'string',  'description' => 'Categoría opcional: Reparacion, Miscelaneo, Vending Machine, Electro, EPTech'],
                    ],
                    'required' => ['anio'],
                ],
            ],
        ];
    }

    public static function sistemaFinanciero(string $fechaActual): string
    {
        return "Eres Wanda, un asistente financiero para un negocio de reparación electrónica. 
    La fecha actual es $fechaActual.
    Tienes acceso a herramientas para consultar datos financieros reales.
    Cuando el usuario pida información financiera, usa las herramientas disponibles para obtener los datos y luego responde en español de forma clara y concisa.
    No inventes números — siempre consulta las herramientas primero.
    Si el usuario pide una corrida de varios meses, consulta cada mes por separado.
    Siempre muestra los montos con el símbolo de dólar y dos decimales, ejemplo: \$1,234.56
    Responde sin markdown ni formato especial, solo texto plano con saltos de línea.";
    }
}