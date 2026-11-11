<?php

namespace App\Contracts\M1_Portal\Exceptions;

/**
 * La noticia está archivada, o ya está publicada y se intentó publicarla de
 * nuevo (ERR-M1-006, contrato 1.4.0). Una archivada no se edita, no se
 * publica ni se vuelve a archivar.
 */
class NoticiaNoModificableException extends PortalPublicoException
{
    protected string $codigo = 'ERR-M1-006';

    protected function mensajePorDefecto(): string
    {
        return 'La noticia no se puede modificar en su estado actual.';
    }
}
