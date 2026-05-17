<?php

namespace Zasetsu\Lookout\Pipeline;

use Zasetsu\Lookout\Trace\ChildEvent;
use Zasetsu\Lookout\Trace\ExecutionContext;

class Filter
{
    public function shouldKeepTrace(ExecutionContext $context): bool
    {
        $ignoreRoutes = config('lookout.filters.ignore_routes', []);
        $ignoreCommands = config('lookout.filters.ignore_commands', []);

        if ($context->type === 'request') {
            foreach ($ignoreRoutes as $pattern) {
                if (fnmatch($pattern, $context->name) || str($context->name)->is($pattern)) {
                    return false;
                }
            }
        }

        if ($context->type === 'command') {
            foreach ($ignoreCommands as $pattern) {
                if (fnmatch($pattern, $context->name) || str($context->name)->is($pattern)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function shouldKeepEvent(ChildEvent $event): bool
    {
        return true;
    }
}
