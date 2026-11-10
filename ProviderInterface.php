<?php

namespace Omniplay;

/**
 * One provider, configured: a store or catalogue of games (Steam, GOG,
 * IGDB...), a way of hosting game servers (a Docker Engine, Edgegap...), a
 * platform that vouches for its players (Steam). Its Capabilities say what
 * it can be asked - search by title (SearchInterface), a game's store record
 * (CatalogueInterface), a player's games (LibraryInterface), whether a
 * player owns a game (OwnershipInterface), start and stop a server of a
 * service (HostingInterface), verify a player's proof (IdentityInterface) -
 * and what its terms ask of whoever shows its records. Nothing on a store
 * is bought, granted or written.
 */
interface ProviderInterface extends \Omnisense\Contracts\Provider\ProviderInterface
{
    /** The factory's name: "steam", "igdb", "docker"... */
    public function getName(): string;

    public function getTitle(): string;

    public function capabilities(): Capabilities;
}
