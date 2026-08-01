<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Framework\Attribute\InjectAlias;

use Gacela\Framework\Attribute\Inject;

final class InjectsOnMethod
{
    private ?CollaboratorInterface $collaborator = null;

    /**
     * The attribute on the method marks it as a setter to call; the one on the
     * parameter names what to pass. Both are the Gacela-namespaced subclass,
     * which is the point of the test -- each is read by a different call site
     * upstream.
     */
    #[Inject]
    public function setCollaborator(
        #[Inject(RedCollaborator::class)]
        CollaboratorInterface $collaborator,
    ): void {
        $this->collaborator = $collaborator;
    }

    public function collaborator(): ?CollaboratorInterface
    {
        return $this->collaborator;
    }
}
