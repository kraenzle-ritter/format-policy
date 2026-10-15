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
        <p class="small text-muted format-policy-order">{{ __('format-policy::rules.order_note') }}</p>

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
                            <h3 class="h6 mb-1"><span class="text-muted">{{ $entry->position }}.</span> {{ $entry->label->in() }}</h3>
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

                    {{-- The rule as «if … except … then …». --}}
                    <dl class="row small mb-2 format-policy-if-then">
                        <dt class="col-sm-2">{{ __('format-policy::rules.if') }}</dt>
                        <dd class="col-sm-10 mb-1">
                            {{ __('format-policy::rules.if_format') }}
                            @if ($entry->mimeTypes !== [])
                                <span class="d-block">{{ __('format-policy::rules.mime_types') }}: <code>{{ implode(', ', $entry->mimeTypes) }}</code></span>
                            @endif
                            @if ($entry->pronomIds !== [])
                                <span class="d-block">{{ __('format-policy::rules.pronom_ids') }}: <code>{{ implode(', ', $entry->pronomIds) }}</code></span>
                            @endif
                            @if ($entry->extensions !== [])
                                <span class="d-block">{{ __('format-policy::rules.extensions') }}: <code>{{ implode(', ', array_map(fn (string $extension): string => '.'.$extension, $entry->extensions)) }}</code></span>
                            @endif
                        </dd>

                        @if ($entry->skipWhen !== [])
                            <dt class="col-sm-2">{{ __('format-policy::rules.except') }}</dt>
                            <dd class="col-sm-10 mb-1">
                                {{ __('format-policy::rules.except_note', ['check' => $entry->precheck?->label->in() ?? '']) }}
                                <ul class="mb-0 ps-3">
                                    @foreach ($entry->skipWhen as $condition)
                                        <li>{{ \KraenzleRitter\FormatPolicy\ConditionText::of($condition) }}</li>
                                    @endforeach
                                </ul>
                            </dd>
                        @endif

                        <dt class="col-sm-2">{{ __('format-policy::rules.then') }}</dt>
                        <dd class="col-sm-10 mb-1">
                            <ol class="mb-1 ps-3">
                                @foreach ($entry->steps as $step)
                                    <li>{{ $step->label->in() }}</li>
                                @endforeach
                            </ol>
                            @if ($entry->verify !== null)
                                <span class="d-block">{{ __('format-policy::rules.verify', ['check' => $entry->verify->label->in()]) }}</span>
                            @endif
                            @if ($entry->requireSmaller)
                                <span class="d-block">{{ __('format-policy::rules.require_smaller') }}</span>
                            @endif
                            <span class="d-block">{{ $entry->keepsOriginal() ? __('format-policy::rules.original_keep') : __('format-policy::rules.original_replace') }}</span>
                        </dd>
                    </dl>

                    {{-- The commands behind the words: tool, version and the call. --}}
                    <details class="small format-policy-technical">
                        <summary class="text-muted">{{ __('format-policy::rules.technical') }}</summary>
                        <table class="table table-sm small mt-2 mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('format-policy::rules.command') }}</th>
                                    <th>{{ __('format-policy::rules.tool') }}</th>
                                    <th>{{ __('format-policy::rules.call') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (array_filter([$entry->precheck, ...$entry->steps, $entry->verify]) as $step)
                                    <tr>
                                        <td><code>{{ $step->command }}</code><span class="d-block text-muted">{{ $step->label->in() }}</span></td>
                                        <td class="text-nowrap">
                                            {{ $step->toolLabel?->in() ?? $step->tool }}
                                            @if ($step->version !== null)
                                                {{ $step->version }}
                                            @else
                                                <span class="text-warning-emphasis">({{ __('format-policy::rules.tool_missing') }})</span>
                                            @endif
                                        </td>
                                        <td><code class="text-break">{{ $step->call }}</code></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </details>

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
