@extends('layouts.app')

@section('title', 'Roles & Permissions — ORWeMS')

@section('main')
    <x-page-header
        title="Roles &amp; Permissions"
        subtitle="Role-based access control — who is responsible for what"
        :breadcrumbs="['Home' => url('/'), 'Administration' => null, 'Roles & Permissions' => null]"
    >
        <x-button variant="outline-primary" size="sm" icon="bi-shield-check" :href="route('administration.permissions.index')">
            Permission Catalogue
        </x-button>
    </x-page-header>

    {{-- Authorization is decided on the server by the `permission` middleware on
         these routes. Reaching this page at all already proves the operator holds
         roles.view; the checks below are presentation only. --}}
    <x-alert type="info" class="mb-4">
        <div>
            <strong>How authorization works in ORWeMS:</strong>
            a role states <em>what</em> an operator is responsible for. Programme and
            geographic scope — <em>where</em> that responsibility applies — is a separate
            concern handled in Phase 3C. No role below is tied to a state, an LGA or a
            single programme.
        </div>
    </x-alert>

    <x-card title="Role Catalogue" subtitle="{{ $roles->count() }} roles defined" bodyClass="p-0">
        <x-table>
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Code</th>
                    <th class="text-center">Permissions</th>
                    <th class="text-center">Operators</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($roles as $role)
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">{{ $role->name }}</div>
                            @if ($role->description)
                                <div class="text-muted small">{{ Str::limit($role->description, 120) }}</div>
                            @endif
                        </td>
                        <td><code class="small">{{ $role->code }}</code></td>
                        <td class="text-center">{{ $role->permissions_count }}</td>
                        <td class="text-center">{{ $role->users_count }}</td>
                        <td>
                            @if ($role->isActive())
                                <x-badge variant="success">Active</x-badge>
                            @else
                                <x-badge variant="secondary">Deactivated</x-badge>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4 small">
                            No roles are defined. Run <code>php artisan db:seed</code> to load the ORWeMS role catalogue.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-table>
    </x-card>

    {{-- Privilege change: POST + CSRF, guarded by users.assign_roles.
         Never a GET, so it cannot be triggered by a link or a prefetch.

         The role select drives the form action via JavaScript. The route
         requires a Role model binding ({role}/assign), so the action cannot be
         static when the operator chooses a role from a list. The JS is minimal
         and inline so it has no external dependency and its intent is readable
         alongside the form that it serves. --}}
    @can('users.assign_roles')
        <x-card title="Assign a Role" subtitle="Grants a responsibility to an operator account" class="mt-4">
            <x-alert type="warning" class="mb-3">
                <div>
                    This is a security-sensitive action. You cannot assign a role to your own
                    account, and an inactive role cannot be assigned at all.
                </div>
            </x-alert>

            <form id="assignRoleForm"
                  method="POST"
                  action=""
                  class="row g-3 align-items-end">
                @csrf

                <div class="col-12 col-md-4">
                    <label for="assign_role_id" class="form-label fw-semibold small">Role</label>
                    <select name="assign_role_id"
                            id="assign_role_id"
                            class="form-select @error('role') is-invalid @enderror"
                            required>
                        <option value="">Select a role…</option>
                        @foreach ($roles->where('is_active', true) as $role)
                            <option value="{{ $role->id }}"
                                    data-action="{{ route('administration.roles.assign', ['role' => $role]) }}">
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('role')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-4">
                    <label for="user_id" class="form-label fw-semibold small">Operator</label>
                    <select name="user_id"
                            id="user_id"
                            class="form-select @error('user_id') is-invalid @enderror"
                            required>
                        <option value="">Select an operator…</option>
                        @foreach (\App\Models\User::orderBy('name')->get() as $operator)
                            <option value="{{ $operator->id }}">{{ $operator->name }} ({{ $operator->email }})</option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12 col-md-4">
                    <button type="submit" id="assignRoleSubmit" class="btn btn-primary" disabled>
                        <i class="bi bi-person-check me-1"></i>Assign Role
                    </button>
                </div>
            </form>
        </x-card>

        <script>
            // Update the form action and enable the submit button when a role
            // is chosen. The data-action attribute holds the pre-generated URL
            // for each role so that no URL construction happens in JavaScript.
            (function () {
                var select = document.getElementById('assign_role_id');
                var form   = document.getElementById('assignRoleForm');
                var submit = document.getElementById('assignRoleSubmit');

                select.addEventListener('change', function () {
                    var option = select.options[select.selectedIndex];
                    var action = option.dataset.action || '';
                    form.action   = action;
                    submit.disabled = !action;
                });
            }());
        </script>
    @endcan
@endsection
