<?php

namespace Omniplay\Model;

enum ImageKind: string
{
    /** The box art, portrait. */
    case Cover = 'cover';
    /** The store's wide banner (Steam's header, a capsule). */
    case Header = 'header';
    case Screenshot = 'screenshot';
    case Background = 'background';
    case Logo = 'logo';
    case Icon = 'icon';
}
