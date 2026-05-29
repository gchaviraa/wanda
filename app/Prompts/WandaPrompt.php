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
    "accion": "resumen" | "nuevo_movimiento" | "inventario" | "modificar_stock" | "desconocido",
    "mes": null | número del mes (1-12),
    "anio": null | año (ejemplo: 2026),
    "mes_relativo": false | true,
    "busqueda": null | texto a buscar en inventario,
    "num_componente": null | número de componente exacto,
    "cantidad_stock": null | número entero (positivo para agregar, negativo para quitar)
    }

    Reglas:
    - "accion" es "resumen" si el usuario quiere ver ingresos, gastos, balance o resumen
    - "accion" es "nuevo_movimiento" si el usuario quiere registrar, agregar o crear un ingreso o gasto
    - "accion" es "inventario" si el usuario pregunta por stock, componentes, partes o inventario
    - "accion" es "modificar_stock" si el usuario quiere agregar o quitar unidades de un componente
    - "accion" es "desconocido" si no entiendes qué quiere
    - "mes" y "anio" solo si el usuario menciona un mes o año específico, si no ponlos en null
    - "mes_relativo" es true si el usuario dice "mes pasado", "el mes anterior" o similar
    - "busqueda" debe contener solo el término técnico a buscar, sin palabras como "busca", "capacitores", "componente", "tienes", "hay", etc.
    - "num_componente" es el número exacto del componente cuando el usuario quiere modificar stock
    - "cantidad_stock" es positivo para agregar, negativo para quitar
    - La fecha actual es: $fechaActual

    Ejemplos:
    - "cómo vamos este mes" → {"accion":"resumen","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null}
    - "resumen de abril" → {"accion":"resumen","mes":4,"anio":2026,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null}
    - "resumen del mes pasado" → {"accion":"resumen","mes":null,"anio":null,"mes_relativo":true,"busqueda":null,"num_componente":null,"cantidad_stock":null}
    - "cuántos 10uF 100V tenemos" → {"accion":"inventario","mes":null,"anio":null,"mes_relativo":false,"busqueda":"10uF 100V","num_componente":null,"cantidad_stock":null}
    - "busca capacitores de 25V" → {"accion":"inventario","mes":null,"anio":null,"mes_relativo":false,"busqueda":"25V","num_componente":null,"cantidad_stock":null}
    - "agrega 5 al IRFB4227PBF" → {"accion":"modificar_stock","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":"IRFB4227PBF","cantidad_stock":5}
    - "quita 2 del UKL2A100MPD1AA" → {"accion":"modificar_stock","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":"UKL2A100MPD1AA","cantidad_stock":-2}
    - "quiero registrar un gasto" → {"accion":"nuevo_movimiento","mes":null,"anio":null,"mes_relativo":false,"busqueda":null,"num_componente":null,"cantidad_stock":null}

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
}