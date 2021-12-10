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

namespace Plugin\Favourite;

use App\Core\DB\DB;
use App\Core\Event;
use App\Core\Modules\NoteHandlerPlugin;
use App\Core\Router\RouteLoader;
use App\Core\Router\Router;
use App\Entity\Activity;
use App\Entity\Actor;
use App\Entity\Feed;
use App\Entity\LocalUser;
use App\Entity\Note;
use App\Util\Common;
use App\Util\Exception\InvalidFormException;
use App\Util\Exception\NoSuchNoteException;
use App\Util\Exception\RedirectException;
use App\Util\Nickname;
use Plugin\Favourite\Entity\Favourite as FavouriteEntity;
use Symfony\Component\HttpFoundation\Request;
use function App\Core\I18n\_m;

class Favourite extends NoteHandlerPlugin
{
    public static function favourNote(int $note_id, int $actor_id, string $source = 'web'): ?Activity
    {
        $opts = ['note_id' => $note_id, 'actor_id' => $actor_id];
        $note_already_favoured = DB::find('favourite', $opts);
        if (is_null($note_already_favoured)) {
            DB::persist(FavouriteEntity::create($opts));
            $act = Activity::create([
                'actor_id' => $actor_id,
                'verb' => 'favourite',
                'object_type' => 'note',
                'object_id' => $note_id,
                'source' => $source,
            ]);
            DB::persist($act);

            Event::handle('NewNotification', [$actor = Actor::getById($actor_id), $act, [], "{$actor->getNickname()} favoured note {$note_id}"]);
        }
        return $act ?? null;
    }

    public static function unfavourNote(int $note_id, int $actor_id, string $source = 'web'): ?Activity
    {
        $note_already_favoured = DB::find('favourite', ['note_id' => $note_id, 'actor_id' => $actor_id]);
        if (!is_null($note_already_favoured)) {
            DB::remove($note_already_favoured);
            $favourite_activity = DB::findBy('activity', ['verb' => 'favourite', 'object_type' => 'note', 'object_id' => $note_id], order_by: ['created' => 'desc'])[0];
            $act = Activity::create([
                'actor_id' => $actor_id,
                'verb' => 'undo', // 'undo_favourite',
                'object_type' => 'activity', // 'note',
                'object_id' => $favourite_activity->getId(), // $note_id,
                'source' => $source,
            ]);
            DB::persist($act);

            Event::handle('NewNotification', [$actor = Actor::getById($actor_id), $act, [], "{$actor->getNickname()} unfavoured note {$note_id}"]);
        }
        return $act ?? null;
    }

    /**
     * HTML rendering event that adds the favourite form as a note
     * action, if a user is logged in
     *
     * @param Request $request
     * @param Note $note
     * @param array $actions
     * @return bool Event hook
     */
    public function onAddNoteActions(Request $request, Note $note, array &$actions): bool
    {
        if (is_null($user = Common::user())) {
            return Event::next;
        }

        // If note is favourite, "is_favourite" is 1
        $opts = ['note_id' => $note->getId(), 'actor_id' => $user->getId()];
        $is_favourite = DB::find('favourite', $opts) !== null;

        // Generating URL for favourite action route
        $args = ['id' => $note->getId()];
        $type = Router::ABSOLUTE_PATH;
        $favourite_action_url = $is_favourite
            ? Router::url('favourite_remove', $args, $type)
            : Router::url('favourite_add', $args, $type);

        $query_string = $request->getQueryString();
        // Concatenating get parameter to redirect the user to where he came from
        $favourite_action_url .= !is_null($query_string) ? '?from=' . mb_substr($query_string, 2) : '';

        $extra_classes = $is_favourite ? 'note-actions-set' : 'note-actions-unset';
        $favourite_action = [
            'url' => $favourite_action_url,
            'title' => $is_favourite ? 'Remove this note from favourites' : 'Favourite this note!',
            'classes' => "button-container favourite-button-container {$extra_classes}",
            'id' => 'favourite-button-container-' . $note->getId(),
        ];

        $actions[] = $favourite_action;
        return Event::next;
    }

    public function onAppendCardNote(array $vars, array &$result)
    {
        // if note is the original, append on end "user favoured this"
        $actor = $vars['actor'];
        $note = $vars['note'];

        return Event::next;
    }

    public function onAddRoute(RouteLoader $r): bool
    {
        // Add/remove note to/from favourites
        $r->connect(id: 'favourite_add', uri_path: '/object/note/{id<\d+>}/favour', target: [Controller\Favourite::class, 'favouriteAddNote']);
        $r->connect(id: 'favourite_remove', uri_path: '/object/note/{id<\d+>}/unfavour', target: [Controller\Favourite::class, 'favouriteRemoveNote']);

        // View all favourites by actor id
        $r->connect(id: 'favourites_view_by_actor_id', uri_path: '/actor/{id<\d+>}/favourites', target: [Controller\Favourite::class, 'favouritesViewByActorId']);
        $r->connect(id: 'favourites_reverse_view_by_actor_id', uri_path: '/actor/{id<\d+>}/reverse_favourites', target: [Controller\Favourite::class, 'favouritesReverseViewByActorId']);

        // View all favourites by nickname
        $r->connect(id: 'favourites_view_by_nickname', uri_path: '/@{nickname<' . Nickname::DISPLAY_FMT . '>}/favourites', target: [Controller\Favourite::class, 'favouritesByActorNickname']);
        $r->connect(id: 'favourites_reverse_view_by_nickname', uri_path: '/@{nickname<' . Nickname::DISPLAY_FMT . '>}/reverse_favourites', target: [Controller\Favourite::class, 'reverseFavouritesByActorNickname']);
        return Event::next;
    }

    public function onCreateDefaultFeeds(int $actor_id, LocalUser $user, int &$ordering)
    {
        DB::persist(Feed::create([
            'actor_id' => $actor_id,
            'url' => Router::url($route = 'favourites_view_by_nickname', ['nickname' => $user->getNickname()]),
            'route' => $route,
            'title' => _m('Favourites'),
            'ordering' => $ordering++,
        ]));
        DB::persist(Feed::create([
            'actor_id' => $actor_id,
            'url' => Router::url($route = 'favourites_reverse_view_by_nickname', ['nickname' => $user->getNickname()]),
            'route' => $route,
            'title' => _m('Reverse favourites'),
            'ordering' => $ordering++,
        ]));
        return Event::next;
    }

    // ActivityPub

    private function activitypub_handler(Actor $actor, \ActivityPhp\Type\AbstractObject $type_activity, mixed $type_object, ?\Plugin\ActivityPub\Entity\ActivitypubActivity &$ap_act): bool
    {
        if (!in_array($type_activity->get('type'), ['Like', 'Undo'])) {
            return Event::next;
        }
        if ($type_activity->get('type') === 'Like') { // Favourite
            if ($type_object instanceof \ActivityPhp\Type\AbstractObject) {
                if ($type_object->get('type') === 'Note') {
                    $note_id = \Plugin\ActivityPub\Util\Model\Note::fromJson($type_object)->getId();
                } else {
                    return Event::next;
                }
            } else if ($type_object instanceof Note) {
                $note_id = $type_object->getId();
            } else {
                return Event::next;
            }
        } else { // Undo Favourite
            if ($type_object instanceof \ActivityPhp\Type\AbstractObject) {
                $ap_prev_favourite_act = \Plugin\ActivityPub\Util\Model\Activity::fromJson($type_object);
                $prev_favourite_act = $ap_prev_favourite_act->getActivity();
                if ($prev_favourite_act->getVerb() === 'favourite' && $prev_favourite_act->getObjectType() === 'note') {
                    $note_id = $prev_favourite_act->getObjectId();
                } else {
                    return Event::next;
                }
            } else if ($type_object instanceof Activity) {
                if ($type_object->getVerb() === 'favourite' && $type_object->getObjectType() === 'note') {
                    $note_id = $type_object->getObjectId();
                } else {
                    return Event::next;
                }
            } else {
                return Event::next;
            }
        }

        if ($type_activity->get('type') === 'Like') {
            $act = self::favourNote($note_id, $actor->getId(), source: 'ActivityPub');
        } else {
            $act = self::unfavourNote($note_id, $actor->getId(), source: 'ActivityPub');
        }
        // Store ActivityPub Activity
        $ap_act = \Plugin\ActivityPub\Entity\ActivitypubActivity::create([
            'activity_id' => $act->getId(),
            'activity_uri' => $type_activity->get('id'),
            'created' => new \DateTime($type_activity->get('published') ?? 'now'),
            'modified' => new \DateTime(),
        ]);
        DB::persist($ap_act);
        return Event::stop;
    }

    public function onNewActivityPubActivity(Actor $actor, \ActivityPhp\Type\AbstractObject $type_activity, \ActivityPhp\Type\AbstractObject $type_object, ?\Plugin\ActivityPub\Entity\ActivitypubActivity &$ap_act): bool
    {
        return $this->activitypub_handler($actor, $type_activity, $type_object, $ap_act);
    }

    public function onNewActivityPubActivityWithObject(Actor $actor, \ActivityPhp\Type\AbstractObject $type_activity, mixed $type_object, ?\Plugin\ActivityPub\Entity\ActivitypubActivity &$ap_act): bool
    {
        return $this->activitypub_handler($actor, $type_activity, $type_object, $ap_act);
    }
    
    public function onGSVerbToActivityStreamsTwoActivityType(string $verb, ?string &$gs_verb_to_activity_stream_two_verb): bool
    {
		if ($verb === 'favourite') {
			$gs_verb_to_activity_stream_two_verb = 'Like';
			return Event::stop;
		}
		return Event::next;
	}
}
