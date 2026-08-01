<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Attribute\InjectAlias;

use Gacela\Framework\Attribute\Inject;

final class InjectsOnProperty
{
    #[Inject(RedCollaborator::class)]
    private CollaboratorInterface $collaborator;

    public function collaborator(): CollaboratorInterface
    {
        return $this->collaborator;
    }
}
