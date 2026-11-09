<?php

namespace Omniplay\Model;

/** The stores and catalogues a game is known by. */
enum Scheme: string
{
    /** Steam: the app id, "620". */
    case Steam = 'steam';
    /** GOG: the product id, "1207658924". */
    case Gog = 'gog';
    /** itch.io: the game's page, "leafo/x-moon" (its number, "3", is what its API answers). */
    case Itch = 'itch';
    /** Epic Games Store: its product slug or namespace, as catalogues give it. */
    case Epic = 'epic';
    /** IGDB: a number. */
    case Igdb = 'igdb';
    /** RAWG: a number (its slug is accepted where RAWG takes it). */
    case Rawg = 'rawg';
}
