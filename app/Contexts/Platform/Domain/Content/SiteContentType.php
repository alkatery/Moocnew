<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Domain\Content;

/**
 * The editing control a site-content field renders as in the admin panel,
 * and how its value is interpreted by the public site.
 */
enum SiteContentType: string
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Color = 'color';
    case Image = 'image';
    case Url = 'url';
}
