<?php

namespace Zasetsu\Lookout\Recorders;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Zasetsu\Lookout\Pipeline\Filter;
use Zasetsu\Lookout\Pipeline\Sampler;
use Zasetsu\Lookout\Support\TraceDispatcher;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

class CommandRecorder implements RecorderContract
{
    private array $commandContexts = [];

    private array $parentContexts = [];

    private array $parentEvents = [];

    public function __construct(
        private TraceBuffer $buffer,
        private Sampler $sampler,
        private Filter $filter,
    ) {}

    public function register(): void
    {
        Event::listen(CommandStarting::class, [$this, 'handleStarting']);
        Event::listen(CommandFinished::class, [$this, 'handleFinished']);
    }

    public function handleStarting(CommandStarting $event): void
    {
        if ($event->command === null) {
            return;
        }

        $context = ExecutionContext::forCommand($event->command);

        if (! $this->sampler->shouldSample($context)) {
            return;
        }

        if (! $this->filter->shouldKeepTrace($context)) {
            return;
        }

        $key = $event->command;

        if ($this->buffer->getContext() !== null) {
            $this->parentContexts[$key][] = $this->buffer->getContext();
            $this->parentEvents[$key][] = $this->buffer->getEvents();
            $this->buffer->clear();
        }

        $this->buffer->setContext($context);
        $this->buffer->markSampled();

        $this->commandContexts[$key][] = $context;
    }

    public function handleFinished(CommandFinished $event): void
    {
        $key = $event->command;
        $context = $this->popCommandContext($key);

        if ($context === null) {
            return;
        }

        $context->finish();

        if ($event->exitCode !== 0) {
            $context->markFailed();
        }

        if ($this->hasParentContext($key)) {
            $data = $this->buffer->flush();

            if ($data['context'] !== null) {
                TraceDispatcher::dispatch($data['context'], $data['events']);
            }

            $this->restoreParentContext($key);
        }
    }

    protected function popCommandContext(string $key): ?ExecutionContext
    {
        if (empty($this->commandContexts[$key])) {
            return null;
        }

        $context = array_pop($this->commandContexts[$key]);

        if (empty($this->commandContexts[$key])) {
            unset($this->commandContexts[$key]);
        }

        return $context;
    }

    protected function hasParentContext(string $key): bool
    {
        return ! empty($this->parentContexts[$key]);
    }

    protected function restoreParentContext(string $key): void
    {
        $parentContext = array_pop($this->parentContexts[$key]);
        $parentEvents = array_pop($this->parentEvents[$key]);

        if (empty($this->parentContexts[$key])) {
            unset($this->parentContexts[$key], $this->parentEvents[$key]);
        }

        $this->buffer->setContext($parentContext);

        foreach ($parentEvents as $evt) {
            $this->buffer->addEvent($evt);
        }

        $this->buffer->markSampled();
    }
}
