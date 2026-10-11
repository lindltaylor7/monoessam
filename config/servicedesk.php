<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Metas de los indicadores
    |--------------------------------------------------------------------------
    |
    | Valores objetivo contra los que el tablero valida cada indicador de la matriz de
    | operacionalizacion. Se dejan en configuracion (y no en codigo) porque son el acuerdo
    | de gestion del area de TI: cambiarlos no debe implicar tocar el calculo.
    |
    | comparator 'gte' -> se cumple cuando el valor es mayor o igual a la meta.
    | comparator 'lte' -> se cumple cuando el valor es menor o igual a la meta (tiempos).
    |
    */

    'targets' => [
        // V1 - Gestion de Incidencias basada en ITIL 4
        'estandarizacion' => 4.0,   // escala ordinal 1..5
        'kedb_usage'      => 60.0,  // % de tickets resueltos con KEDB
        'classification'  => 90.0,  // % de tickets categorizados correctamente

        // V2 - Eficiencia del Area de Sistemas
        'mttr'            => 480.0, // minutos (8 horas)
        'mttr_response'   => 30.0,  // minutos
        'sla_compliance'  => 95.0,  // %
        'satisfaction'    => 4.0,   // escala Likert 1..5
    ],

    /*
    |--------------------------------------------------------------------------
    | Umbral de alerta
    |--------------------------------------------------------------------------
    |
    | Holgura relativa para marcar un indicador "en riesgo" en vez de "no cumple".
    | 0.10 = hasta un 10% por debajo (o por encima, en los de tiempo) de la meta.
    |
    */

    'warning_tolerance' => 0.10,

    /*
    |--------------------------------------------------------------------------
    | Muestra minima
    |--------------------------------------------------------------------------
    |
    | Numero de casos por debajo del cual el indicador se calcula pero se marca como no
    | concluyente. Evita leer un 100% de cumplimiento construido con dos tickets.
    |
    */

    'min_sample' => [
        'tickets' => 10,
        'surveys' => 10,
        'maturity' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cobertura minima de las fuentes
    |--------------------------------------------------------------------------
    |
    | Porcentaje de tickets que deberian estar auditados / encuestados para que los
    | indicadores que dependen de esas fuentes se consideren representativos.
    |
    */

    'min_coverage' => [
        'audit'  => 20.0,
        'survey' => 30.0,
    ],

];
