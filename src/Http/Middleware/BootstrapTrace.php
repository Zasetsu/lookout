<?php

namespace Zasetsu\Lookout\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Zasetsu\Lookout\Pipeline\Filter;
use Zasetsu\Lookout\Pipeline\Sampler;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

class BootstrapTrace
{
    public function __construct(
        private TraceBuffer $buffer,
        private Sampler $sampler,
        private Filter $filter,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->attributes->has('_lookout_trace_bootstrapped')) {
            return $next($request);
        }

        $request->attributes->set('_lookout_trace_bootstrapped', true);

        $context = ExecutionContext::forRequest($request);

        try {
            $sampled = $this->sampler->shouldSample($context);
            $this->sampler->recordSample($context);
        } catch (\Throwable) {
            $sampled = true;
        }

        if ($sampled && $this->filter->shouldKeepTrace($context)) {
            $this->buffer->setContext($context);
            $this->buffer->markSampled();
        }

        return $next($request);
    }
}
