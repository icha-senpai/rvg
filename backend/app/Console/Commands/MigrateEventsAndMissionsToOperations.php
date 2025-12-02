<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\EventMember;
use App\Models\EventRole;
use App\Models\Mission;
use App\Models\MissionMember;
use App\Models\Operation;
use App\Models\OperationParticipant;
use App\Models\OperationRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateEventsAndMissionsToOperations extends Command
{
    protected $signature = 'ops:migrate-events-missions';

    protected $description = 'Migrate existing events and missions to unified operations tables';

    public function handle(): int
    {
        $this->info('Starting migration of events and missions into operations...');

        DB::beginTransaction();

        try {
            $mapEventToOp   = [];
            $mapMissionToOp = [];

            // 1) Events -> Operations
            $this->info('Migrating events to operations...');

            foreach (Event::cursor() as $event) {
                $op = Operation::create([
                    'squadron_id'         => $event->squadron_id ?? null,
                    'created_by'          => $event->created_by ?? $event->user_id ?? 1, // adjust if needed
                    'title'               => $event->title,
                    'description'         => $event->description ?? null,
                    'starts_at'           => $event->starts_at,
                    'ends_at'             => $event->ends_at ?? null,
                    'visibility'          => $event->visibility ?? 'open',
                    'operation_kind'      => 'event',
                    'type'                => $event->event_type ?? null,
                    'difficulty'          => $event->difficulty ?? null,
                    'operation_strictness'=> $event->event_strictness ?? null,
                    'icon'                => $event->icon ?? null,
                    'image_url'           => $event->image_url ?? null,
                    'rsvp_deadline'       => $event->rsvp_deadline ?? null,
                    'notes'               => $event->notes ?? null,
                    'status'              => $event->status ?? 'draft',
                    'cancellation_reason' => $event->cancellation_reason ?? null,
                    'slots'               => null,
                ]);

                $mapEventToOp[$event->id] = $op->id;
            }

            // 2) EventRoles -> OperationRoles
            $this->info('Migrating event roles to operation roles...');

            foreach (EventRole::cursor() as $role) {
                $operationId = $mapEventToOp[$role->event_id] ?? null;

                if (!$operationId) {
                    $this->warn("Skipping EventRole id={$role->id}, no mapped operation.");
                    continue;
                }

                OperationRole::create([
                    'operation_id'      => $operationId,
                    'role_name'         => $role->role,
                    'role_display_name' => $role->role,
                    'capacity'          => $role->capacity ?? null,
                    'min_required'      => $role->min_required ?? 0,
                    'description'       => null,
                    'requirements'      => null,
                ]);
            }

            // 3) EventMembers -> OperationParticipants
            $this->info('Migrating event members to operation participants...');

            foreach (EventMember::with('eventRole')->cursor() as $m) {
                $operationId = $mapEventToOp[$m->event_id] ?? null;

                if (!$operationId) {
                    $this->warn("Skipping EventMember id={$m->id}, no mapped operation.");
                    continue;
                }

                $roleId = null;

                if ($m->eventRole) {
                    $roleId = OperationRole::where('operation_id', $operationId)
                        ->where('role_name', $m->eventRole->role)
                        ->value('id');
                }

                OperationParticipant::create([
                    'operation_id'       => $operationId,
                    'user_id'            => $m->user_id,
                    'operation_role_id'  => $roleId,
                    'slot'               => $m->role ?? null,
                    'attendance_status'  => $m->attendance_status ?? 'signed_up',
                    'notes'              => $m->notes ?? null,
                    'stats'              => $m->stats ?? null,
                ]);
            }

            // 4) Missions -> Operations
            $this->info('Migrating missions to operations...');

            foreach (Mission::cursor() as $mission) {
                $op = Operation::create([
                    'squadron_id'         => $mission->squadron_id ?? null,
                    'created_by'          => $mission->created_by ?? $mission->user_id ?? 1,
                    'title'               => $mission->title,
                    'description'         => $mission->description ?? null,
                    'starts_at'           => $mission->starts_at,
                    'ends_at'             => $mission->ends_at ?? null,
                    'visibility'          => $mission->visibility ?? 'open',
                    'operation_kind'      => 'mission',
                    'type'                => $mission->mission_type ?? null,
                    'difficulty'          => $mission->difficulty ?? null,
                    'operation_strictness'=> $mission->mission_strictness ?? null,
                    'icon'                => $mission->icon ?? null,
                    'image_url'           => $mission->image_url ?? null,
                    'rsvp_deadline'       => $mission->rsvp_deadline ?? null,
                    'notes'               => $mission->notes ?? null,
                    'status'              => $mission->status ?? 'draft',
                    'cancellation_reason' => $mission->cancellation_reason ?? null,
                    'slots'               => $mission->slots ?? null,
                ]);

                $mapMissionToOp[$mission->id] = $op->id;
            }

            // 5) MissionMembers -> OperationParticipants
            $this->info('Migrating mission members to operation participants...');

            foreach (MissionMember::cursor() as $m) {
                $operationId = $mapMissionToOp[$m->mission_id] ?? null;

                if (!$operationId) {
                    $this->warn("Skipping MissionMember id={$m->id}, no mapped operation.");
                    continue;
                }

                OperationParticipant::create([
                    'operation_id'       => $operationId,
                    'user_id'            => $m->user_id,
                    'operation_role_id'  => null,
                    'slot'               => $m->slot ?? null,
                    'attendance_status'  => $m->attendance_status ?? 'signed_up',
                    'notes'              => $m->notes ?? null,
                    'stats'              => $m->stats ?? null,
                ]);
            }

            DB::commit();

            $this->info('Migration completed successfully.');

            $this->line('Totals:');
            $this->line('  Operations: ' . Operation::count());
            $this->line('  Operation participants: ' . OperationParticipant::count());

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Migration failed: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
