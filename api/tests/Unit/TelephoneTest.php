<?php

namespace Tests\Unit;

use App\Support\Telephone;
use Tests\TestCase;

class TelephoneTest extends TestCase
{
    public function test_numero_local_a_9_chiffres_recoit_l_indicatif(): void
    {
        $this->assertSame('+237692342106', Telephone::normaliser('692342106'));
    }

    public function test_zero_initial_est_retire_avant_l_indicatif(): void
    {
        $this->assertSame('+237692342106', Telephone::normaliser('0692342106'));
    }

    public function test_numero_deja_au_format_e164_est_inchange(): void
    {
        $this->assertSame('+237692342106', Telephone::normaliser('+237692342106'));
    }

    public function test_indicatif_present_sans_le_plus_n_est_pas_double(): void
    {
        $this->assertSame('+237692342106', Telephone::normaliser('237692342106'));
    }

    public function test_espaces_et_separateurs_sont_ignores(): void
    {
        $this->assertSame('+237692342106', Telephone::normaliser('237 692 34 21 06'));
    }
}
