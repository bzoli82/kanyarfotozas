<?php

namespace App\Mail\Concerns;

use App\Services\MailTemplates;

/**
 * A superadmin altal a /admin/settings/mail feluleten testreszabhato szoveges
 * blokkokat (targy, cimsor, bevezeto, zaro, alairas) koti be a mailable-ba.
 */
trait UsesMailTemplate
{
    /** @var array<string, string>|null */
    private ?array $resolvedTemplate = null;

    abstract protected function templateKey(): string;

    /**
     * @return array<string, string|int|null>
     */
    abstract protected function templateData(): array;

    /**
     * @return array<string, string>
     */
    protected function template(): array
    {
        return $this->resolvedTemplate ??= MailTemplates::resolve($this->templateKey(), $this->templateData());
    }

    protected function templateSubject(): string
    {
        return $this->template()['subject'];
    }

    /**
     * A blade nezetnek atadando szoveges blokkok (targy nelkul).
     *
     * @return array<string, string>
     */
    protected function templateBlocks(): array
    {
        $blocks = $this->template();
        unset($blocks['subject']);

        return $blocks;
    }
}
