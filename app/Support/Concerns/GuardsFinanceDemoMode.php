<?php

namespace App\Support\Concerns;

use Illuminate\Validation\ValidationException;

trait GuardsFinanceDemoMode
{
    protected function isFinanceDemoModeEnabled(): bool
    {
        return (bool) config('finance.demo_mode', true);
    }

    protected function assertFinanceDemoModeEnabled(
        string $errorKey = 'finance',
        string $message = 'Fitur keuangan demo sedang nonaktif.'
    ): void {
        if ($this->isFinanceDemoModeEnabled()) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => $message,
        ]);
    }
}
