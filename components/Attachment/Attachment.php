<?php

declare(strict_types = 1);

// {{{ License
// This file is part of GNU social - https://www.gnu.org/software/social
//
// GNU social is free software: you can redistribute it and/or modify
// it under the terms of the GNU Affero General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// GNU social is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU Affero General Public License for more details.
//
// You should have received a copy of the GNU Affero General Public License
// along with GNU social.  If not, see <http://www.gnu.org/licenses/>.
// }}}

namespace Component\Attachment;

use App\Core\Event;
use App\Core\Modules\Component;
use App\Core\Router\RouteLoader;
use Component\Attachment\Controller as C;

class Attachment extends Component
{
    public function onAddRoute(RouteLoader $r): bool
    {
        $r->connect('attachment_show', '/attachment/{id<\d+>}', [C\Attachment::class, 'attachment_show']);
        $r->connect('attachment_view', '/attachment/{id<\d+>}/view', [C\Attachment::class, 'attachment_view']);
        $r->connect('attachment_download', '/attachment/{id<\d+>}/download', [C\Attachment::class, 'attachment_download']);
        $r->connect('attachment_thumbnail', '/attachment/{id<\d+>}/thumbnail/{size<big|medium|small>}', [C\Attachment::class, 'attachment_thumbnail']);
        return Event::next;
    }
}