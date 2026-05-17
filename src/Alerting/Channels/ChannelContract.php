<?php

namespace Zasetsu\Lookout\Alerting\Channels;

interface ChannelContract
{
    public function send(object $threshold, array $context): void;
}
