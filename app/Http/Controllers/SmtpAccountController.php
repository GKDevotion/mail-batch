<?php

namespace App\Http\Controllers;

use App\Http\Requests\SmtpAccountRequest;
use App\Models\SmtpAccount;
use App\Services\SmtpAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmtpAccountController extends Controller
{
    public function __construct(private readonly SmtpAccountService $accounts)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SmtpAccount::class);

        return view('smtp-accounts.index', [
            'accounts' => $request->user()->smtpAccounts()->withCount('campaigns')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', SmtpAccount::class);

        return view('smtp-accounts.create');
    }

    public function store(SmtpAccountRequest $request): RedirectResponse
    {
        $this->accounts->create($request->user(), $request->validated());

        return redirect()->route('smtp-accounts.index')->with('status', 'SMTP account saved. The password is encrypted and will not be shown again.');
    }

    public function edit(SmtpAccount $smtpAccount): View
    {
        $this->authorize('update', $smtpAccount);

        return view('smtp-accounts.edit', ['account' => $smtpAccount]);
    }

    public function update(SmtpAccountRequest $request, SmtpAccount $smtpAccount): RedirectResponse
    {
        $this->accounts->update($smtpAccount, $request->validated());

        return redirect()->route('smtp-accounts.index')->with('status', 'SMTP account updated.');
    }

    public function destroy(SmtpAccount $smtpAccount): RedirectResponse
    {
        $this->authorize('delete', $smtpAccount);

        $smtpAccount->delete();

        return redirect()->route('smtp-accounts.index')->with('status', 'SMTP account deleted.');
    }
}
