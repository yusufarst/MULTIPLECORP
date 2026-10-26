<?php

namespace App\Console\Commands;

/** The operator commands' text inputs, an absent or non-text value read as empty (TASK-020). */
trait ReadsOperatorInput
{
    protected function optionText(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : '';
    }

    protected function argumentText(string $name): string
    {
        $value = $this->argument($name);

        return is_string($value) ? $value : '';
    }
}
