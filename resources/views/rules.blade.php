{{--
    The rules of the format policy for one tenant (anton#655), Bootstrap 5,
    no Livewire: Anton and agate run different Livewire versions.

    Expects:
      $overview      KraenzleRitter\FormatPolicy\RuleOverview
      $editable      bool — a form with one switch per rule, or read only
      $action        string|null — where the form posts (editable only)
      $method        string|null — PUT, PATCH … sent as `_method`
      $managedBy     array{text: string, url?: string}|null — read only: where the rules are set
      $sourceLabels  array<string, string> — names for the ingest routes; the key otherwise

    The form sends `rules[<key>]` = 1 or 0 for every rule it shows, which
    RuleSwitches::apply() takes as it is. Saving, permission and logging stay
    with the calling program.
--}}
@php
    $editable = (bool) ($editable ?? false);
    $sourceLabels = $sourceLabels ?? [];
    $routeNames = fn (array $keys): string => implode(', ', array_map(fn (string $key): string => $sourceLabels[$key] ?? $key, $keys));
    $entries = $overview->admitting();
@endphp

<div class="format-policy-rules">
    @if (! $editable && ! empty($managedBy))
        <div class="alert alert-light small format-policy-managed-by">
            @if (! empty($managedBy['url']))
                <a href="{{ $managedBy['url'] }}">{{ $managedBy['text'] }}</a>
            @else
                {{ $managedBy['text'] }}
            @endif
        </div>
    @endif

    @if ($entries === [])
        <p class="text-muted">{{ __('format-policy::rules.none') }}</p>
    @else
        @if ($editable)
            <form method="POST" action="{{ $action }}">
                @csrf
                @if (! empty($method))
                    @method($method)
                @endif
        @endif

        @foreach ($entries as $entry)
            <div class="card mb-3 format-policy-rule" data-rule="{{ $entry->key }}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <h3 class="h6 mb-1">{{ $entry->label->in() }}</h3>
                            <div class="small">
                                @if ($entry->inForce)
                                    <span class="badge bg-success">{{ __('format-policy::rules.in_force') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ __('format-policy::rules.off') }}</span>
                                @endif
                                <span class="text-muted ms-1">
                                    {{ $entry->override === null ? __('format-policy::rules.follows_default') : __('format-policy::rules.own_setting') }}
                                    ({{ $entry->enabledByDefault ? __('format-policy::rules.default_on') : __('format-policy::rules.default_off') }})
                                </span>
                            </div>
                        </div>
                        @if ($editable)
                            <div class="form-check form-switch fs-5 mb-0">
                                <input type="hidden" name="rules[{{ $entry->key }}]" value="0">
                                <input class="form-check-input" type="checkbox" role="switch"
                                       id="format-policy-rule-{{ $entry->key }}"
                                       name="rules[{{ $entry->key }}]" value="1"
                                       aria-label="{{ $entry->label->in() }}"
                                       @checked($entry->inForce)>
                            </div>
                        @endif
                    </div>

                    <p class="small mt-2 mb-2">{{ $entry->description->in() }}</p>

                    <div class="small text-muted">
                        {{ __('format-policy::rules.routes') }}:
                        @if ($entry->sources !== null)
                            {{ __('format-policy::rules.routes_only', ['list' => $routeNames($entry->sources)]) }}
                        @elseif ($entry->excludeSources !== [])
                            {{ __('format-policy::rules.routes_except', ['list' => $routeNames($entry->excludeSources)]) }}
                        @else
                            {{ __('format-policy::rules.routes_all') }}
                        @endif
                    </div>

                    @unless ($entry->runsHere())
                        <div class="small text-warning-emphasis mt-1 format-policy-not-runnable">
                            {{ __('format-policy::rules.not_runnable') }}
                        </div>
                    @endunless
                </div>
            </div>
        @endforeach

        @if ($editable)
                <button type="submit" class="btn btn-primary">{{ __('format-policy::rules.save') }}</button>
            </form>
        @endif
    @endif
</div>
