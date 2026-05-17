<?php

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage as IlluminateSentMessage;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Zasetsu\Lookout\Pipeline\Redactor;
use Zasetsu\Lookout\Recorders\MailRecorder;
use Zasetsu\Lookout\Trace\ExecutionContext;
use Zasetsu\Lookout\Trace\TraceBuffer;

describe('MailRecorder', function () {
    it('redacts sensitive mail metadata before recording it', function () {
        $buffer = new TraceBuffer;
        $context = new ExecutionContext;
        $context->type = 'request';
        $context->name = 'POST /password/email';

        $buffer->setContext($context);
        $buffer->markSampled();

        $email = (new Email)
            ->from('support@example.com')
            ->to('user@example.com')
            ->subject('Password reset token=mail-secret-token')
            ->text('Body is not recorded.');

        $sent = new IlluminateSentMessage(
            new SymfonySentMessage(
                $email,
                new Envelope(
                    new Address('support@example.com'),
                    [new Address('user@example.com')]
                )
            )
        );

        $recorder = new MailRecorder($buffer, new Redactor);
        $recorder->handleMessageSent(new MessageSent($sent));

        $event = $buffer->getEvents()[0];

        expect($event->labels)->toContain('token=***');
        expect($event->labels)->not->toContain('mail-secret-token');
        expect($event->payload['subject'])->toContain('token=***');
        expect($event->payload['subject'])->not->toContain('mail-secret-token');
    });
});
