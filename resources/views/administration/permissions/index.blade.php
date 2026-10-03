@extends('layouts.app')

@section('title', 'Permission Catalogue — ORWeMS')

@section('main')
    <x-page-header
        title="Permission Catalogue"
        subtitle="Every capability ORWeMS defines, grouped by resource family"
        :breadcrumbs="['Home' => url('/'), 'Administration' => null, 'Permission Catalogue' => null]"
    >
        <x-button variant="outline-secondary" size="sm" icon="bi-shield-lock" :href="route('administration.roles.index')">
            Role Catalogue
        </x-button>
    </x-page-header>

    <x-alert type="info" class="mb-4">
        <div>
            <strong>About permissions:</strong>
            each permission is a named capability (<code>&lt;resource&gt;.&lt;action&gt;</code>). Permissions are granted
            to <em>roles</em>, not to individuals. The stable <code>code</code> is what authorization
            compares; the human-readable name may be reworded at any time without changing what
            any operator is allowed to do.
        </div>
    </x-alert>

    <div class="row g-3 mb-3">
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="display-6 fw-bold text-primary">{{ $groups->flatten()->count() }}</div>
                <div class="small text-muted">Total permissions</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="display-6 fw-bold text-primary">{{ $groups->count() }}</div>
                <div class="small text-muted">Resource families</div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card border-0 shadow-sm text-center py-3">
                <div class="display-6 fw-bold text-{{ $groups->flatten()->count() === $catalogueSize ? 'success' : 'warning' }}">
                    {{ $groups->flatten()->where('is_active', true)->count() }}
                </div>
                <div class="small text-muted">Active permissions</div>
            </div>
        </div>
    </div>

    @foreach ($groups as $family => $permissions)
        <x-card
            title="{{ ucfirst(str_replace('_', ' ', $family)) }}"
            subtitle="{{ $permissions->count() }} {{ Str::plural('permission', $permissions->count()) }}"
            bodyClass="p-0"
            class="mb-3"
        >
            <x-table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($permissions as $permission)
                        <tr>
                            <td><code class="small">{{ $permission->code }}</code></td>
                            <td class="fw-semibold small">{{ $permission->name }}</td>
                            <td class="text-muted small">{{ $permission->description }}</td>
                            <td>
                                @if ($permission->isActive())
                                    <x-badge variant="success">Active</x-badge>
                                @else
                                    <x-badge variant="secondary">Retired</x-badge>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-table>
        </x-card>
    @endforeach
@endsection
