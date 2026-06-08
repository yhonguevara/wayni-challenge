<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\IndexDebtorsRequest;
use App\Http\Requests\ShowDebtorRequest;
use App\Http\Requests\TopDebtorsRequest;
use App\Http\Resources\DebtorResource;
use App\Models\Debtor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class DebtorController extends Controller
{
    public function show(ShowDebtorRequest $request, string $cuit): DebtorResource
    {
        $debtor = Debtor::where('identification_number', $cuit)->firstOrFail();

        return new DebtorResource($debtor);
    }

    public function top(TopDebtorsRequest $request): AnonymousResourceCollection
    {
        $n = $request->validated('n');

        $debtors = Debtor::orderByDesc('total_loan_amount')
            ->limit($n)
            ->get();

        return DebtorResource::collection($debtors);
    }

    public function index(IndexDebtorsRequest $request): AnonymousResourceCollection
    {
        $situation = $request->validated('situation');
        $perPage = $request->validated('per_page', 50);

        $query = Debtor::query();

        if ($situation !== null) {
            $query->where('max_situation', $situation);
        }

        return DebtorResource::collection($query->paginate($perPage));
    }
}
