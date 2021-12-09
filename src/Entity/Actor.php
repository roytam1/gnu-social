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

namespace App\Entity;

use App\Core\Cache;
use App\Core\DB\DB;
use App\Core\Entity;
use App\Core\Event;
use App\Core\Router\Router;
use App\Core\UserRoles;
use App\Util\Exception\NicknameException;
use App\Util\Exception\NotFoundException;
use App\Util\Nickname;
use Component\Avatar\Avatar;
use Component\Tag\Tag as TagComponent;
use DateTimeInterface;
use Functional as F;

/**
 * Entity for actors
 *
 * @category  DB
 * @package   GNUsocial
 *
 * @author    Zach Copley <zach@status.net>
 * @copyright 2010 StatusNet Inc.
 * @author    Mikael Nordfeldth <mmn@hethane.se>
 * @copyright 2009-2014 Free Software Foundation, Inc http://www.fsf.org
 * @author    Hugo Sales <hugo@hsal.es>
 * @copyright 2020-2021 Free Software Foundation, Inc http://www.fsf.org
 * @license   https://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 */
class Actor extends Entity
{
    // {{{ Autocode
    // @codeCoverageIgnoreStart
    private int $id;
    private string $nickname;
    private ?string $fullname = null;
    private int $roles        = 4;
    private ?string $homepage;
    private ?string $bio;
    private ?string $location;
    private ?float $lat;
    private ?float $lon;
    private ?int $location_id;
    private ?int $location_service;
    private bool $is_local;
    private DateTimeInterface $created;
    private DateTimeInterface $modified;

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setNickname(string $nickname): self
    {
        $this->nickname = $nickname;
        return $this;
    }

    public function getNickname(): string
    {
        return $this->nickname;
    }

    public function setFullname(?string $fullname): self
    {
        $this->fullname = $fullname;
        return $this;
    }

    public function getFullname(): ?string
    {
        if (\is_null($this->fullname)) {
            return null;
        }
        return $this->fullname;
    }

    public function setRoles(int $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getRoles(): int
    {
        return $this->roles;
    }

    public function setHomepage(?string $homepage): self
    {
        $this->homepage = $homepage;
        return $this;
    }

    public function getHomepage(): ?string
    {
        return $this->homepage;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLat(?float $lat): self
    {
        $this->lat = $lat;
        return $this;
    }

    public function getLat(): ?float
    {
        return $this->lat;
    }

    public function setLon(?float $lon): self
    {
        $this->lon = $lon;
        return $this;
    }

    public function getLon(): ?float
    {
        return $this->lon;
    }

    public function setLocationId(?int $location_id): self
    {
        $this->location_id = $location_id;
        return $this;
    }

    public function getLocationId(): ?int
    {
        return $this->location_id;
    }

    public function setLocationService(?int $location_service): self
    {
        $this->location_service = $location_service;
        return $this;
    }

    public function getLocationService(): ?int
    {
        return $this->location_service;
    }

    public function setIsLocal(bool $is_local): self
    {
        $this->is_local = $is_local;
        return $this;
    }

    public function getIsLocal(): bool
    {
        return $this->is_local;
    }

    public function setCreated(DateTimeInterface $created): self
    {
        $this->created = $created;
        return $this;
    }

    public function getCreated(): DateTimeInterface
    {
        return $this->created;
    }

    public function setModified(DateTimeInterface $modified): self
    {
        $this->modified = $modified;
        return $this;
    }

    public function getModified(): DateTimeInterface
    {
        return $this->modified;
    }

    // @codeCoverageIgnoreEnd
    // }}} Autocode

    public static function cacheKeys(int $actor_id, mixed $other = null): array
    {
        return [
            'id'                => "actor-id-{$actor_id}",
            'nickname'          => "actor-nickname-id-{$actor_id}",
            'fullname'          => "actor-fullname-id-{$actor_id}",
            'tags'              => \is_null($other) ? "actor-circles-and-tags-{$actor_id}" : "actor-circles-and-tags-{$actor_id}-by-{$other}", // $other is $context_id
            'subscriber'        => "subscriber-{$actor_id}",
            'subscribed'        => "subscribed-{$actor_id}",
            'relative-nickname' => "actor-{$actor_id}-relative-nickname-{$other}", // $other is $nickname
        ];
    }

    public function getLocalUser()
    {
        if ($this->getIsLocal()) {
            return DB::findOneBy('local_user', ['id' => $this->getId()]);
        } else {
            throw new NotFoundException('This is a remote actor.');
        }
    }

    public function isGroup()
    {
        // TODO: implement
        return false;
    }

    public function getAvatarUrl(string $size = 'full')
    {
        return Avatar::getUrl($this->getId(), $size);
    }

    public function getAvatarDimensions(string $size = 'full')
    {
        return Avatar::getDimensions($this->getId(), $size);
    }

    public static function getById(int $id): ?self
    {
        return Cache::get(self::cacheKeys($id)['id'], fn () => DB::find('actor', ['id' => $id]));
    }

    public static function getNicknameById(int $id): string
    {
        return Cache::get(self::cacheKeys($id)['nickname'], fn () => self::getById($id)->getNickname());
    }

    public static function getFullnameById(int $id): ?string
    {
        return Cache::get(self::cacheKeys($id)['fullname'], fn () => self::getById($id)->getFullname());
    }

    /**
     * For consistency with Note
     */
    public function getActorId(): int
    {
        return $this->getId();
    }

    /**
     * Tags attributed to self, shortcut function for increased legibility
     *
     * @return array<int, array> [ActorCircle[], ActorTag[]] resulting lists
     */
    public function getSelfTags(bool $_test_force_recompute = false): array
    {
        return $this->getOtherTags(context: $this->getId(), _test_force_recompute: $_test_force_recompute);
    }

    /**
     * Get tags that other people put on this actor, in reverse-chron order
     *
     * @param null|Actor|int $context Actor we are requesting as:
     *                                - If null = All tags attributed to self by other actors (excludes self tags)
     *                                - If self = Same as getSelfTags
     *                                - otherwise = Tags that $context attributed to $this
     * @param null|int       $offset  Offset from latest
     * @param null|int       $limit   Max number to get
     *
     * @return array<int, array> [ActorCircle[], ActorTag[]] resulting lists
     */
    public function getOtherTags(self|int|null $context = null, ?int $offset = null, ?int $limit = null, bool $_test_force_recompute = false): array
    {
        if (\is_null($context)) {
            return Cache::get(
                self::cacheKeys($this->getId())['tags'],
                fn () => DB::dql(
                    <<< 'EOQ'
                        SELECT circle, tag
                        FROM actor_tag tag
                              JOIN actor_circle circle
                              WITH tag.tagger = circle.tagger
                                   AND tag.tag = circle.tag
                        WHERE tag.tagged = :id
                        ORDER BY tag.modified DESC, tag.tagged DESC
                        EOQ,
                    ['id' => $this->getId()],
                    options: ['offset' => $offset, 'limit' => $limit],
                ),
            );
        } else {
            $context_id = \is_int($context) ? $context : $context->getId();
            return Cache::get(
                self::cacheKeys($this->getId(), $context_id)['tags'],
                fn () => DB::dql(
                    <<< 'EOQ'
                        SELECT circle, tag
                        FROM actor_tag tag
                             JOIN actor_circle circle
                             WITH tag.tagger = circle.tagger
                                  AND tag.tag = circle.tag
                        WHERE
                             tag.tagged = :id
                             AND (circle.private != true
                                  OR (circle.tagger = :scoped
                                      AND circle.private = true
                                  )
                             )
                        ORDER BY tag.modified DESC, tag.tagged DESC
                        EOQ,
                    ['id' => $this->getId(), 'scoped' => $context_id],
                    options: ['offset' => $offset, 'limit' => $limit],
                ),
            );
        }
    }

    /**
     * @param array      $tags     array of strings to become self tags
     * @param null|array $existing array of existing self tags (ActorTag[])
     *
     * @throws \App\Util\Exception\DuplicateFoundException
     * @throws NotFoundException
     *
     * @return $this
     */
    public function setSelfTags(array $tags, ?array $existing = null): self
    {
        if (\is_null($existing)) {
            [$_, $existing] = $this->getSelfTags();
        }
        $existing_actor_tags  = F\map($existing, fn ($actor_tag) => $actor_tag->getTag());
        $tags_to_add          = array_diff($tags, $existing_actor_tags);
        $tags_to_remove       = array_diff($existing_actor_tags, $tags);
        $actor_tags_to_remove = F\filter($existing, fn ($actor_tag) => \in_array($actor_tag->getTag(), $tags_to_remove));
        foreach ($tags_to_add as $tag) {
            $canonical_tag = TagComponent::canonicalTag($tag, $this->getTopLanguage()->getLocale());
            DB::persist(ActorCircle::create(['tagger' => $this->getId(), 'tag' => $canonical_tag, 'private' => false]));
            DB::persist(ActorTag::create(['tagger' => $this->id, 'tagged' => $this->id, 'tag' => $tag, 'canonical' => $canonical_tag, 'use_canonical' => true])); // TODO make use canonical configurable
        }
        foreach ($actor_tags_to_remove as $actor_tag) {
            $canonical_tag = TagComponent::canonicalTag($actor_tag->getTag(), $this->getTopLanguage()->getLocale());
            DB::removeBy('actor_tag', ['tagger' => $this->getId(), 'tagged' => $this->getId(), 'canonical' => $canonical_tag]);
            DB::removeBy('actor_circle', ['tagger' => $this->getId(), 'tag' => $canonical_tag]); // TODO only remove if unused
        }
        Cache::delete(self::cacheKeys($this->getId())['tags']);
        Cache::delete(self::cacheKeys($this->getId(), $this->getId())['tags']);
        return $this;
    }

    private function getSubCount(string $which, string $column): int
    {
        return Cache::get(
            self::cacheKeys($this->getId())[$which],
            fn () => DB::dql(
                "select count(s) from subscription s where s.{$column} = :{$column}", // Not injecting the parameter value
                [$column => $this->getId()],
            )[0][1] - ($this->getIsLocal() ? 1 : 0), // Remove self subscription if local
        );
    }

    public function getSubscribersCount(): int
    {
        return $this->getSubCount(which: 'subscriber', column: 'subscribed');
    }

    public function getSubscribedCount()
    {
        return $this->getSubCount(which: 'subscribed', column: 'subscriber');
    }

    public function isPerson(): bool
    {
        return ($this->roles & UserRoles::BOT) === 0;
    }

    /**
     * Resolve an ambiguous nickname reference, checking in following order:
     * - Actors that $sender subscribes to
     * - Actors that subscribe to $sender
     * - Any Actor
     *
     * @param string $nickname validated nickname of
     *
     * @throws NicknameException
     */
    public function findRelativeActor(string $nickname): ?self
    {
        // Will throw exception on invalid input.
        $nickname = Nickname::normalize($nickname, check_already_used: false);
        return Cache::get(
            self::cacheKeys($this->getId(), $nickname)['relative-nickname'],
            fn () => DB::dql(
                <<<'EOF'
                    select a from actor a where
                    a.id in (select fa.subscribed from subscription fa join actor aa with fa.subscribed = aa.id where fa.subscriber = :actor_id and aa.nickname = :nickname) or
                    a.id in (select fb.subscriber from subscription fb join actor ab with fb.subscriber = ab.id where fb.subscribed = :actor_id and ab.nickname = :nickname) or
                    a.nickname = :nickname
                    EOF,
                ['nickname' => $nickname, 'actor_id' => $this->getId()],
                ['limit'    => 1],
            )[0] ?? null,
        );
    }

    public function getUri(int $type = Router::ABSOLUTE_URL): string
    {
        $uri = null;
        if (Event::handle('StartGetActorUri', [$this, $type, &$uri]) === Event::next) {
            $uri = Router::url('actor_view_id', ['id' => $this->getId()], $type);
            Event::handle('EndGetActorUri', [$this, $type, &$uri]);
        }
        return $uri;
    }

    public function getUrl(int $type = Router::ABSOLUTE_URL): string
    {
        $url = null;
        if (Event::handle('StartGetActorUrl', [$this, $type, &$url]) === Event::next) {
            if ($this->getIsLocal()) {
                $url = Router::url('actor_view_nickname', ['nickname' => $this->getNickname()], $type);
            } else {
                return $this->getUri($type);
            }
            Event::handle('EndGetActorUrl', [$this, $type, &$url]);
        }
        return $url;
    }

    public function getAliases(): array
    {
        return array_keys($this->getAliasesWithIDs());
    }

    public function getAliasesWithIDs(): array
    {
        $aliases = [];

        $aliases[$this->getUri(Router::ABSOLUTE_URL)] = $this->getId();
        $aliases[$this->getUrl(Router::ABSOLUTE_URL)] = $this->getId();

        return $aliases;
    }

    public function getTopLanguage(): Language
    {
        return ActorLanguage::getActorLanguages($this, context: null)[0];
    }

    /**
     * Get the most appropriate language for $this to use when
     * referring to $context (a reply or a group, for instance)
     *
     * @return Language[]
     */
    public function getPreferredLanguageChoices(?self $context = null): array
    {
        $langs = ActorLanguage::getActorLanguages($this, context: $context);
        return array_merge(...F\map($langs, fn ($l) => $l->toChoiceFormat()));
    }

    public function isVisibleTo(null|LocalUser|self $other): bool
    {
        return true; // TODO
    }

    public static function schemaDef(): array
    {
        return [
            'name'        => 'actor',
            'description' => 'local and remote users, groups and bots are actors, for instance',
            'fields'      => [
                'id'               => ['type' => 'serial', 'not null' => true, 'description' => 'unique identifier'],
                'nickname'         => ['type' => 'varchar', 'length' => 64, 'not null' => true, 'description' => 'nickname or username'],
                'fullname'         => ['type' => 'text', 'description' => 'display name'],
                'roles'            => ['type' => 'int', 'not null' => true, 'default' => UserRoles::USER, 'description' => 'Bitmap of permissions this actor has'],
                'homepage'         => ['type' => 'text', 'description' => 'identifying URL'],
                'bio'              => ['type' => 'text', 'description' => 'descriptive biography'],
                'location'         => ['type' => 'text', 'description' => 'physical location'],
                'lat'              => ['type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'latitude'],
                'lon'              => ['type' => 'numeric', 'precision' => 10, 'scale' => 7, 'description' => 'longitude'],
                'location_id'      => ['type' => 'int', 'description' => 'location id if possible'],
                'location_service' => ['type' => 'int', 'description' => 'service used to obtain location id'],
                'is_local'         => ['type' => 'bool', 'not null' => true, 'description' => 'Does this actor have a LocalUser associated'],
                'created'          => ['type' => 'datetime', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was created'],
                'modified'         => ['type' => 'timestamp', 'not null' => true, 'default' => 'CURRENT_TIMESTAMP', 'description' => 'date this record was modified'],
            ],
            'primary key' => ['id'],
            'indexes'     => [
                'actor_nickname_idx' => ['nickname'],
            ],
            'fulltext indexes' => [
                'actor_fulltext_idx' => ['nickname', 'fullname', 'location', 'bio', 'homepage'],
            ],
        ];
    }
}
