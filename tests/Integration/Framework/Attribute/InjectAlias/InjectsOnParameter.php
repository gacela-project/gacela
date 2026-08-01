<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Attribute\InjectAlias;

use Gacela\Framework\Attribute\Inject;

final class InjectsOnParameter
{
    public function __construct(
        #[Inject(RedCollaborator::class)]
        public readonly CollaboratorInterface $collaborator,
    ) {
    }
}
