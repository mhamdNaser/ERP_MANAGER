<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * مهمة العمل انتقلت من الموارد البشرية إلى فرع الآليات، فتنتقل معها
 * طلباتها وسجل قراراتها. الرقم المرجعي يبقى كما صدر كي تبقى الوثائق
 * المطبوعة مطابقةً لسجلها، وتُترجم مرحلة الموارد البشرية إلى مرحلة الآليات.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            foreach (DB::table('hr_requests')->where('type', 'mission')->orderBy('id')->get() as $request) {
                $missionId = DB::table('fleet_missions')->insertGetId([
                    'reference_code' => $request->reference_code,
                    'user_id' => $request->user_id,
                    'created_by_id' => $request->created_by_id,
                    'type' => 'mission',
                    'start_date' => $request->start_date,
                    'end_date' => $request->end_date,
                    'days' => $request->days,
                    'destination' => $request->destination,
                    'reason' => $request->reason,
                    'status' => $this->toFleetStatus($request->status),
                    'stage' => $this->toFleetStage($request->stage),
                    'attachment_path' => $request->attachment_path,
                    'attachment_name' => $request->attachment_name,
                    'decided_at' => $request->decided_at,
                    'document_number' => $request->document_number,
                    'qr_payload' => $request->qr_payload,
                    'word_path' => $request->word_path,
                    'pdf_path' => $request->pdf_path,
                    'created_at' => $request->created_at,
                    'updated_at' => $request->updated_at,
                ]);

                foreach (DB::table('hr_request_actions')->where('hr_request_id', $request->id)->orderBy('id')->get() as $action) {
                    DB::table('fleet_mission_actions')->insert([
                        'fleet_mission_id' => $missionId,
                        'actor_id' => $action->actor_id,
                        'stage' => $this->toFleetStage($action->stage),
                        'action' => $action->action,
                        'note' => $action->note,
                        'created_at' => $action->created_at,
                        'updated_at' => $action->updated_at,
                    ]);
                }
            }

            // حذف الطلب يجرّ سجل قراراته بقيد المفتاح الأجنبي.
            DB::table('hr_requests')->where('type', 'mission')->delete();
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach (DB::table('fleet_missions')->where('type', 'mission')->orderBy('id')->get() as $mission) {
                $requestId = DB::table('hr_requests')->insertGetId([
                    'reference_code' => $mission->reference_code,
                    'user_id' => $mission->user_id,
                    'created_by_id' => $mission->created_by_id,
                    'type' => 'mission',
                    'subtype' => null,
                    'start_date' => $mission->start_date,
                    'end_date' => $mission->end_date,
                    'days' => $mission->days,
                    'destination' => $mission->destination,
                    'reason' => $mission->reason,
                    'status' => $this->toHrStatus($mission->status),
                    'stage' => $this->toHrStage($mission->stage),
                    'attachment_path' => $mission->attachment_path,
                    'attachment_name' => $mission->attachment_name,
                    'decided_at' => $mission->decided_at,
                    'document_number' => $mission->document_number,
                    'qr_payload' => $mission->qr_payload,
                    'word_path' => $mission->word_path,
                    'pdf_path' => $mission->pdf_path,
                    'created_at' => $mission->created_at,
                    'updated_at' => $mission->updated_at,
                ]);

                foreach (DB::table('fleet_mission_actions')->where('fleet_mission_id', $mission->id)->orderBy('id')->get() as $action) {
                    DB::table('hr_request_actions')->insert([
                        'hr_request_id' => $requestId,
                        'actor_id' => $action->actor_id,
                        'stage' => $this->toHrStage($action->stage),
                        'action' => $action->action,
                        'note' => $action->note,
                        'created_at' => $action->created_at,
                        'updated_at' => $action->updated_at,
                    ]);
                }
            }

            DB::table('fleet_missions')->where('type', 'mission')->delete();
        });
    }

    private function toFleetStage(?string $stage): string
    {
        return match ($stage) {
            'head', 'hr' => 'fleet',
            null => 'fleet',
            default => $stage,
        };
    }

    private function toFleetStatus(?string $status): string
    {
        return match ($status) {
            'pending_head', 'pending_hr' => 'pending_fleet',
            null => 'pending_fleet',
            default => $status,
        };
    }

    private function toHrStage(?string $stage): string
    {
        return $stage === 'fleet' ? 'hr' : ($stage ?: 'hr');
    }

    private function toHrStatus(?string $status): string
    {
        return $status === 'pending_fleet' ? 'pending_hr' : ($status ?: 'pending_hr');
    }
};
