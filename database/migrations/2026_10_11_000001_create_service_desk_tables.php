<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modulo Service Desk (ITIL 4).
 *
 * El esquema esta disenado para que los siete indicadores de la matriz de
 * operacionalizacion se calculen con datos reales del sistema, no capturados a mano:
 *
 *  V1 - Gestion de Incidencias basada en ITIL 4
 *    - Nivel de estandarizacion del flujo de incidencias  -> maturity_* (cuestionario a TI)
 *    - Uso de la base de conocimientos (KEDB)             -> tickets.resolved_with_kedb
 *    - Clasificacion correcta por impacto/urgencia        -> ticket_audits (auditoria de logs)
 *
 *  V2 - Eficiencia del Area de Sistemas
 *    - MTTR    -> tickets.reported_at .. resolved_at
 *    - MTTRsp  -> tickets.reported_at .. first_response_at
 *    - SLA     -> tickets.response_due_at / resolution_due_at (congelados al crear el ticket)
 *    - CSAT    -> satisfaction_surveys (cuestionario Likert a usuarios)
 *
 * Las fechas de vencimiento de SLA se guardan en el ticket en vez de recalcularse al
 * consultar: si manana cambia la politica de SLA, el cumplimiento historico no se reescribe.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Catalogo de categorias de incidencia (arbol de un nivel: categoria / subcategoria).
        Schema::create('service_desk_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('service_desk_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Politica de SLA por prioridad (P1..P4). En minutos, para que sea comparable con MTTR/MTTRsp.
        Schema::create('service_desk_slas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedTinyInteger('priority')->unique();
            $table->unsignedInteger('response_minutes');
            $table->unsignedInteger('resolution_minutes');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // KEDB: base de datos de errores conocidos (practica de Gestion del Conocimiento de ITIL 4).
        Schema::create('service_desk_kedb_articles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('title');
            $table->text('symptoms')->nullable();
            $table->text('root_cause')->nullable();
            $table->text('workaround')->nullable();
            $table->text('solution')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('service_desk_categories')->nullOnDelete();
            // borrador | publicado | retirado
            $table->string('status', 20)->default('publicado');
            $table->unsignedInteger('times_used')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('title');
            $table->index('status');
        });

        Schema::create('service_desk_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('title');
            $table->text('description')->nullable();

            // Solicitante: puede ser un usuario del sistema o alguien externo registrado a mano.
            $table->foreignId('requester_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requester_name')->nullable();
            $table->string('requester_email')->nullable();
            $table->string('requester_area')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();

            // portal | telefono | correo | presencial | whatsapp
            $table->string('channel', 20)->default('portal');
            $table->foreignId('category_id')->nullable()->constrained('service_desk_categories')->nullOnDelete();

            // Matriz ITIL: prioridad = f(impacto, urgencia). 1=Alto/Alta .. 3=Bajo/Baja.
            $table->unsignedTinyInteger('impact')->default(3);
            $table->unsignedTinyInteger('urgency')->default(3);
            // 1=Critica, 2=Alta, 3=Media, 4=Baja
            $table->unsignedTinyInteger('priority')->default(4);

            $table->foreignId('sla_id')->nullable()->constrained('service_desk_slas')->nullOnDelete();
            // nuevo | asignado | en_proceso | en_espera | resuelto | cerrado
            $table->string('status', 20)->default('nuevo');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('reported_at');
            $table->dateTime('response_due_at')->nullable();
            $table->dateTime('resolution_due_at')->nullable();
            $table->dateTime('first_response_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();

            $table->text('resolution_notes')->nullable();
            $table->foreignId('kedb_article_id')->nullable()->constrained('service_desk_kedb_articles')->nullOnDelete();
            $table->boolean('resolved_with_kedb')->default(false);
            $table->unsignedSmallInteger('reopened_count')->default(0);
            $table->timestamps();

            $table->index('status');
            $table->index('priority');
            $table->index('reported_at');
            $table->index('resolved_at');
            $table->index('assigned_to');
        });

        // Bitacora del ticket: es el "Service Desk Log" que respalda la fuente de datos.
        Schema::create('service_desk_ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('service_desk_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // creado | asignado | respondido | comentado | estado | resuelto | cerrado | reabierto | auditado
            $table->string('type', 30);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });

        // Auditoria de clasificacion: un ticket, una auditoria vigente (se actualiza, no se duplica).
        Schema::create('service_desk_ticket_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->unique()->constrained('service_desk_tickets')->cascadeOnDelete();
            $table->foreignId('auditor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('category_correct')->default(true);
            $table->boolean('impact_correct')->default(true);
            $table->boolean('urgency_correct')->default(true);
            $table->boolean('priority_correct')->default(true);
            // AND de los cuatro criterios: es el numerador del indicador de clasificacion correcta.
            $table->boolean('is_correct')->default(true);
            $table->foreignId('suggested_category_id')->nullable()->constrained('service_desk_categories')->nullOnDelete();
            $table->unsignedTinyInteger('suggested_impact')->nullable();
            $table->unsignedTinyInteger('suggested_urgency')->nullable();
            $table->text('findings')->nullable();
            $table->dateTime('audited_at');
            $table->timestamps();

            $table->index('is_correct');
        });

        // Encuesta Likert (1..5) al usuario final, enlazada al ticket atendido.
        Schema::create('service_desk_satisfaction_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->nullable()->unique()->constrained('service_desk_tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('respondent_name')->nullable();
            $table->string('respondent_area')->nullable();
            // Percepcion global 1..5 (indicador V2 - Calidad Percibida del Servicio).
            $table->unsignedTinyInteger('score');
            $table->unsignedTinyInteger('q_attention')->nullable();
            $table->unsignedTinyInteger('q_response_time')->nullable();
            $table->unsignedTinyInteger('q_solution')->nullable();
            $table->unsignedTinyInteger('q_communication')->nullable();
            $table->text('comments')->nullable();
            $table->dateTime('answered_at');
            $table->timestamps();

            $table->index('answered_at');
        });

        // Cuestionario de madurez ITIL 4 aplicado al personal de TI (escala ordinal 1..5).
        Schema::create('service_desk_maturity_questions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            // estandarizacion | conocimiento | priorizacion
            $table->string('dimension', 40);
            $table->text('statement');
            $table->unsignedSmallInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_desk_maturity_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('respondent_name');
            $table->string('respondent_role')->nullable();
            // YYYY-MM
            $table->string('period', 7);
            $table->dateTime('answered_at');
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->index('period');
        });

        Schema::create('service_desk_maturity_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('service_desk_maturity_assessments')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('service_desk_maturity_questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->timestamps();

            $table->unique(['assessment_id', 'question_id']);
        });

        $this->seedDefaults();
    }

    /**
     * Datos minimos para que el modulo sea usable sin configuracion previa: la matriz de SLA
     * por prioridad, las categorias tipicas de una mesa de ayuda y el cuestionario de madurez
     * ITIL 4 que alimenta el indicador de estandarizacion del flujo.
     */
    private function seedDefaults(): void
    {
        $now = now();

        DB::table('service_desk_slas')->insert([
            ['name' => 'P1 - Critica', 'priority' => 1, 'response_minutes' => 15,  'resolution_minutes' => 240,  'description' => 'Servicio critico caido o multiples usuarios sin operar.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'P2 - Alta',    'priority' => 2, 'response_minutes' => 30,  'resolution_minutes' => 480,  'description' => 'Degradacion severa con impacto en un area completa.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'P3 - Media',   'priority' => 3, 'response_minutes' => 60,  'resolution_minutes' => 1440, 'description' => 'Afecta a un usuario con alternativa temporal.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'P4 - Baja',    'priority' => 4, 'response_minutes' => 240, 'resolution_minutes' => 4320, 'description' => 'Consulta o requerimiento sin impacto operativo.', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $categories = [
            ['code' => 'HW',  'name' => 'Hardware',               'description' => 'Equipos de computo, impresoras y perifericos.'],
            ['code' => 'SW',  'name' => 'Software',               'description' => 'Sistema operativo, ofimatica y aplicaciones de escritorio.'],
            ['code' => 'RED', 'name' => 'Red y conectividad',     'description' => 'Enlaces, wifi, VPN y telefonia IP.'],
            ['code' => 'ERP', 'name' => 'Sistemas de negocio',    'description' => 'ERP, POS y modulos internos.'],
            ['code' => 'ACC', 'name' => 'Accesos y seguridad',    'description' => 'Altas, bajas, contrasenas y permisos.'],
            ['code' => 'SRV', 'name' => 'Servidores y servicios', 'description' => 'Correo, backups, servidores y base de datos.'],
        ];

        foreach ($categories as $category) {
            DB::table('service_desk_categories')->insert($category + [
                'parent_id'  => null,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $questions = [
            ['code' => 'EST-01', 'dimension' => 'estandarizacion', 'statement' => 'Existe un flujo documentado de gestion de incidencias (registro, categorizacion, diagnostico, resolucion y cierre) conocido por todo el equipo de TI.'],
            ['code' => 'EST-02', 'dimension' => 'estandarizacion', 'statement' => 'Toda incidencia se registra en la herramienta antes de atenderse, sin importar el canal por el que llega.'],
            ['code' => 'EST-03', 'dimension' => 'estandarizacion', 'statement' => 'Los estados del ticket y los criterios para cambiarlos estan definidos y se aplican de forma uniforme.'],
            ['code' => 'EST-04', 'dimension' => 'estandarizacion', 'statement' => 'Existen criterios de escalamiento (funcional y jerarquico) formalizados y se respetan en la practica.'],
            ['code' => 'EST-05', 'dimension' => 'estandarizacion', 'statement' => 'El cierre del ticket exige registrar la solucion aplicada antes de darlo por terminado.'],
            ['code' => 'CON-01', 'dimension' => 'conocimiento',    'statement' => 'Ante una incidencia nueva se consulta primero la base de errores conocidos (KEDB) antes de diagnosticar desde cero.'],
            ['code' => 'CON-02', 'dimension' => 'conocimiento',    'statement' => 'Las soluciones y soluciones temporales nuevas se documentan en la KEDB de forma sistematica.'],
            ['code' => 'CON-03', 'dimension' => 'conocimiento',    'statement' => 'Los articulos de la KEDB se revisan y actualizan periodicamente para mantenerlos vigentes.'],
            ['code' => 'PRI-01', 'dimension' => 'priorizacion',    'statement' => 'La prioridad del ticket se determina con la matriz de impacto por urgencia y no por criterio personal.'],
            ['code' => 'PRI-02', 'dimension' => 'priorizacion',    'statement' => 'Las categorias de incidencia estan definidas y se usan de manera consistente por todo el equipo.'],
            ['code' => 'PRI-03', 'dimension' => 'priorizacion',    'statement' => 'Los acuerdos de nivel de servicio (SLA) por prioridad estan publicados y son conocidos por los usuarios.'],
            ['code' => 'PRI-04', 'dimension' => 'priorizacion',    'statement' => 'Se revisa periodicamente si los tickets fueron categorizados y priorizados correctamente.'],
        ];

        foreach ($questions as $index => $question) {
            DB::table('service_desk_maturity_questions')->insert($question + [
                'order'      => $index + 1,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_desk_maturity_answers');
        Schema::dropIfExists('service_desk_maturity_assessments');
        Schema::dropIfExists('service_desk_maturity_questions');
        Schema::dropIfExists('service_desk_satisfaction_surveys');
        Schema::dropIfExists('service_desk_ticket_audits');
        Schema::dropIfExists('service_desk_ticket_events');
        Schema::dropIfExists('service_desk_tickets');
        Schema::dropIfExists('service_desk_kedb_articles');
        Schema::dropIfExists('service_desk_slas');
        Schema::dropIfExists('service_desk_categories');
    }
};
