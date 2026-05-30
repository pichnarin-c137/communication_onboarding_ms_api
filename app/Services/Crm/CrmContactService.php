<?php

namespace App\Services\Crm;

use App\Exceptions\Business\CrmContactNotFoundException;
use App\Models\CrmContact;
use App\Services\Logging\ActivityLogger;

class CrmContactService
{
    public function __construct(
        private readonly ActivityLogger $activityLogger,
    ) {}

    /**
     * withCount expression for deals that are not in a terminal stage.
     */
    private function activeDealsCount(): array
    {
        return ['deals as active_deals_count' => function ($query): void {
            $query->whereNotIn('stage', config('coms.crm.deal_terminal_stages'));
        }];
    }

    public function list(array $filters, int $perPage, int $page): array
    {
        $query = CrmContact::query()
            ->with(['businessType:id,name_en', 'creator:id,first_name,last_name'])
            ->withCount($this->activeDealsCount())
            ->orderByDesc('created_at');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($builder) use ($search): void {
                $builder->where('company_name', 'ilike', "%$search%")
                    ->orWhere('contact_name', 'ilike', "%$search%")
                    ->orWhere('phone', 'ilike', "%$search%");
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['business_type_id'])) {
            $query->where('business_type_id', $filters['business_type_id']);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())
                ->map(fn (CrmContact $c) => CrmPresenter::contact($c))
                ->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];
    }

    /**
     * @throws CrmContactNotFoundException
     */
    public function get(string $id): CrmContact
    {
        $contact = CrmContact::query()
            ->with(['businessType:id,name_en', 'creator:id,first_name,last_name'])
            ->withCount($this->activeDealsCount())
            ->find($id);

        if (! $contact) {
            throw new CrmContactNotFoundException(
                "CRM contact with ID '$id' not found.",
                context: ['crm_contact_id' => $id]
            );
        }

        return $contact;
    }

    public function create(array $data, ?string $createdBy): CrmContact
    {
        $contact = CrmContact::create([
            ...$data,
            'status' => 'prospect',
            'created_by' => $createdBy,
        ]);

        $this->activityLogger->log(
            ActivityLogger::CRM_CONTACT_CREATED,
            "CRM contact '$contact->company_name' created",
            ['crm_contact_id' => $contact->id],
            $createdBy,
        );

        return $this->get($contact->id);
    }

    public function update(CrmContact $contact, array $data): CrmContact
    {
        $contact->update($data);

        $this->activityLogger->log(
            ActivityLogger::CRM_CONTACT_UPDATED,
            "CRM contact '$contact->company_name' updated",
            ['crm_contact_id' => $contact->id],
        );

        return $this->get($contact->id);
    }

    public function delete(CrmContact $contact): void
    {
        $contactId = $contact->id;
        $companyName = $contact->company_name;

        $contact->delete();

        $this->activityLogger->log(
            ActivityLogger::CRM_CONTACT_DELETED,
            "CRM contact '$companyName' deleted",
            ['crm_contact_id' => $contactId],
        );
    }

    /**
     * Deals belonging to a contact, shaped for the contract.
     *
     * @throws CrmContactNotFoundException
     */
    public function listDeals(string $contactId): array
    {
        $contact = $this->get($contactId);

        return $contact->deals()
            ->with(['assignee:id,first_name,last_name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($deal) => CrmPresenter::deal($deal))
            ->all();
    }
}
