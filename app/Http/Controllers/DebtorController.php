<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DebtorController extends Controller
{
    /**
     * Customers with their invoicing preferences and what they still owe.
     */
    public function index(Request $request): View
    {
        $sort = in_array($request->query('sort'), ['name', 'payment_term_days', 'created_at'], true) ? $request->query('sort') : 'name';
        $direction = $request->query('dir') === 'desc' ? 'desc' : 'asc';

        $debtors = Organization::query()
            ->with(['invoiceContactPerson', 'invoices.lines', 'invoices.payments'])
            ->whereIn('type', ['customer', 'lead', 'prospect', 'partner'])
            ->filter($request->query())
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return view('debtors.index', ['debtors' => $debtors, 'sort' => $sort, 'direction' => $direction]);
    }
}
