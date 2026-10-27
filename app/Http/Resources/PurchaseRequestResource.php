<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'requester' => $this->whenLoaded('requester', fn (): array => [
                'id' => $this->requester->id,
                'name' => $this->requester->name,
                'email' => $this->requester->email,
            ]),
            'department' => $this->whenLoaded('department', fn (): array => [
                'id' => $this->department->id,
                'name' => $this->department->name,
            ]),
            'category' => $this->whenLoaded('category', fn (): array => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'justification' => $this->justification,
            'urgency' => $this->urgency,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'submission_round' => $this->submission_round,
            'approvals' => $this->whenLoaded('approvals', fn (): array => $this->approvals
                ->map(fn ($approval): array => [
                    'stage' => $approval->stage,
                    'decision' => $approval->decision,
                    'approver' => $approval->approver?->name,
                    'reason' => $approval->reason,
                    'acted_at' => $approval->acted_at?->toISOString(),
                    'submission_round' => $approval->submission_round,
                ])
                ->all()),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
