<?php
/*
 * StatusNet - the distributed open-source microblogging tool
 * Copyright (C) 2011, StatusNet, Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

if (!defined('STATUSNET')) {
    exit(1);
}

/**
 * Class to represent extended profile data
 */
class ExtendedProfile
{
    protected $fields;

    /**
     * Constructor
     *
     * @param Profile $profile
     * @throws NoSuchUserException
     */
    public function __construct(Profile $profile)
    {
        $this->profile = $profile;
        $this->user = $profile->getUser();
        $this->fields = $this->loadFields();
        $this->sections = $this->getSections();
        //common_debug(var_export($this->sections, true));

        //common_debug(var_export($this->fields, true));
    }

    /**
     * Load extended profile fields
     *
     * @return array $fields the list of fields
     * @throws Exception
     */
    public function loadFields()
    {
        $detail = new Profile_detail();
        $detail->profile_id = $this->profile->getID();
        $detail->find();

        $fields = [];

        while ($detail->fetch()) {
            $fields[$detail->field_name][] = clone($detail);
        }

        return $fields;
    }

    /**
     * Get a the self-tags associated with this profile
     *
     * @return string the concatenated string of tags
     */
    public function getTags()
    {
        return implode(' ', Profile_tag::getSelfTagsArray($this->profile));
    }

    /**
     * Return a simple string value. Checks for fields that should
     * be stored in the regular profile and returns values from it
     * if appropriate.
     *
     * @param string $name name of the detail field to get the
     *                     value from
     *
     * @return string|null the value, null if none
     */
    public function getTextValue($name): ?string
    {
        $key = strtolower($name);
        $profileFields = ['fullname', 'location', 'bio'];

        if (in_array($key, $profileFields)) {
            return $this->profile->$name;
        } elseif (array_key_exists($key, $this->fields)) {
            return $this->fields[$key][0]->field_value;
        } else {
            return null;
        }
    }

    public function getDateValue($name)
    {
        $key = strtolower($name);
        if (array_key_exists($key, $this->fields)) {
            return $this->fields[$key][0]->date;
        } else {
            return null;
        }
    }

    // XXX: getPhones, getIms, and getWebsites pretty much do the same thing,
    //      so refactor.
    public function getPhones()
    {
        $phones = (isset($this->fields['phone'])) ? $this->fields['phone'] : null;
        $pArrays = [];

        if (empty($phones)) {
            $pArrays[] = array(
                // TRANS: Field label for extended profile properties.
                'label' => _m('Phone'),
                'index' => 0,
                'type' => 'phone',
                'vcard' => 'tel',
                'rel' => 'office',
                'value' => null
            );
        } else {
            for ($i = 0; $i < sizeof($phones); $i++) {
                $pa = array(
                    // TRANS: Field label for extended profile properties.
                    'label' => _m('Phone'),
                    'type' => 'phone',
                    'index' => intval($phones[$i]->value_index),
                    'rel' => $phones[$i]->rel,
                    'value' => $phones[$i]->field_value,
                    'vcard' => 'tel'
                );

                $pArrays[] = $pa;
            }
        }
        return $pArrays;
    }

    public function getIms()
    {
        $ims = (isset($this->fields['im'])) ? $this->fields['im'] : null;
        $iArrays = [];

        if (empty($ims)) {
            $iArrays[] = array(
                // TRANS: Field label for extended profile properties (Instant Messaging).
                'label' => _m('IM'),
                'type' => 'im'
            );
        } else {
            for ($i = 0; $i < sizeof($ims); $i++) {
                $ia = array(
                    // TRANS: Field label for extended profile properties (Instant Messaging).
                    'label' => _m('IM'),
                    'type' => 'im',
                    'index' => intval($ims[$i]->value_index),
                    'rel' => $ims[$i]->rel,
                    'value' => $ims[$i]->field_value,
                );

                $iArrays[] = $ia;
            }
        }
        return $iArrays;
    }

    public function getWebsites()
    {
        $sites = (isset($this->fields['website'])) ? $this->fields['website'] : null;
        $wArrays = [];

        if (empty($sites)) {
            $wArrays[] = array(
                // TRANS: Field label for extended profile properties.
                'label' => _m('Website'),
                'type' => 'website'
            );
        } else {
            for ($i = 0; $i < sizeof($sites); $i++) {
                $wa = array(
                    // TRANS: Field label for extended profile properties.
                    'label' => _m('Website'),
                    'type' => 'website',
                    'index' => intval($sites[$i]->value_index),
                    'rel' => $sites[$i]->rel,
                    'value' => $sites[$i]->field_value,
                );

                $wArrays[] = $wa;
            }
        }
        return $wArrays;
    }

    public function getExperiences()
    {
        $companies = (isset($this->fields['company'])) ? $this->fields['company'] : null;
        $start = (isset($this->fields['start'])) ? $this->fields['start'] : null;
        $end = (isset($this->fields['end'])) ? $this->fields['end'] : null;

        $eArrays = [];

        if (empty($companies)) {
            $eArrays[] = array(
                // TRANS: Field label for extended profile properties.
                'label' => _m('Employer'),
                'type' => 'experience',
                'company' => null,
                'start' => null,
                'end' => null,
                'current' => false,
                'index' => 0
            );
        } else {
            for ($i = 0; $i < sizeof($companies); $i++) {
                $ea = array(
                    // TRANS: Field label for extended profile properties.
                    'label' => _m('Employer'),
                    'type' => 'experience',
                    'company' => $companies[$i]->field_value,
                    'index' => intval($companies[$i]->value_index),
                    'current' => $end[$i]->rel,
                    'start' => $start[$i]->date,
                    'end' => $end[$i]->date
                );
                $eArrays[] = $ea;
            }
        }
        return $eArrays;
    }

    public function getEducation()
    {
        $schools = (isset($this->fields['school'])) ? $this->fields['school'] : null;
        $degrees = (isset($this->fields['degree'])) ? $this->fields['degree'] : null;
        $descs = (isset($this->fields['degree_descr'])) ? $this->fields['degree_descr'] : null;
        $start = (isset($this->fields['school_start'])) ? $this->fields['school_start'] : null;
        $end = (isset($this->fields['school_end'])) ? $this->fields['school_end'] : null;
        $iArrays = [];

        if (empty($schools)) {
            $iArrays[] = array(
                'type' => 'education',
                // TRANS: Field label for extended profile properties.
                'label' => _m('Institution'),
                'school' => null,
                'degree' => null,
                'description' => null,
                'start' => null,
                'end' => null,
                'index' => 0
            );
        } else {
            for ($i = 0; $i < sizeof($schools); $i++) {
                $ia = array(
                    'type' => 'education',
                    // TRANS: Field label for extended profile properties.
                    'label' => _m('Institution'),
                    'school' => $schools[$i]->field_value,
                    'degree' => isset($degrees[$i]->field_value) ? $degrees[$i]->field_value : null,
                    'description' => isset($descs[$i]->field_value) ? $descs[$i]->field_value : null,
                    'index' => intval($schools[$i]->value_index),
                    'start' => $start[$i]->date,
                    'end' => $end[$i]->date
                );
                $iArrays[] = $ia;
            }
        }

        return $iArrays;
    }

    /**
     *  Return all the sections of the extended profile
     *
     * @return array the big list of sections and fields
     * @throws Exception
     */
    public function getSections()
    {
        $display_extra = false;
        $gsefields = GNUsocialProfileExtensionField::allFields();
        $extra_fields = [];
        gnusocial_profile_merge($this->profile);
        foreach ($gsefields as $field) {
            $display_extra = true;
            $field_key = $field->systemname;
            switch ($field->type) {
                case 'text':
                    $extra_fields[$field_key]['type'] = 'custom-textarea';
                    break;
                case 'str':
                default:
                    $extra_fields[$field_key]['type'] = 'custom-text';
                    break;
            }
            $extra_fields[$field_key]['label'] = $field->title;
            $extra_fields[$field_key]['value'] = $this->profile->$field_key;
        }

        $sections = [
            'basic' => [
                // TRANS: Field label for extended profile properties.
                'label' => _m('Personal'),
                'fields' => [
                    'fullname' => [
                        // TRANS: Field label for extended profile properties.
                        'label' => _m('Full name'),
                        'profile' => 'fullname',
                        'vcard' => 'fn',
                    ],
                    'title' => [
                        // TRANS: Field label for extended profile properties.
                        'label' => _m('Title'),
                        'vcard' => 'title',
                    ],
                    'manager' => [
                        // TRANS: Field label for extended profile properties.
                        'label' => _m('Manager'),
                        'type' => 'person',
                        'vcard' => 'x-manager',
                    ],
                    'location' => [
                        // TRANS: Field label for extended profile properties.
                        'label' => _m('Location'),
                        'profile' => 'location'
                    ],
                    'bio' => [
                        // TRANS: Field label for extended profile properties.
                        'label' => _m('Bio'),
                        'type' => 'textarea',
                        'profile' => 'bio',
                    ],
                    'tags' => [
                        // TRANS: Field label for extended profile properties.
                        'label' => _m('Tags'),
                        'type' => 'tags',
                        'profile' => 'tags',
                    ],
                ],
            ],
            'contact' => [
                // TRANS: Field label for extended profile properties.
                'label' => _m('Contact'),
                'fields' => [
                    'phone' => $this->getPhones(),
                    'im' => $this->getIms(),
                    'website' => $this->getWebsites()
                ],
            ],
            'personal' => [
                // TRANS: Field label for extended profile properties.
                'label' => _m('Personal'),
                'fields' => [
                    'birthday' => [
                        // TRANS: Field label for extended profile properties.
                        'label' => _m('Birthday'),
                        'type' => 'date',
                        'vcard' => 'bday',
                    ],
                    'spouse' => [
                        // TRANS: Field label for extended profile properties.
                        'label' => _m('Spouse\'s name'),
                        'vcard' => 'x-spouse',
                    ],
                    'kids' => [
                        // TRANS: Field label for extended profile properties.
                        'label' => _m('Kids\' names')
                    ],
                ],
            ],
            'experience' => [
                // TRANS: Field label for extended profile properties.
                'label' => _m('Work experience'),
                'fields' => [
                    'experience' => $this->getExperiences()
                ],
            ],
            'education' => [
                // TRANS: Field label for extended profile properties.
                'label' => _m('Education'),
                'fields' => [
                    'education' => $this->getEducation()
                ],
            ],
        ];
        if ($display_extra) {
            $sections['extra'] = [
                'label' => _m('Additional information'),
                'fields' => $extra_fields,
            ];
        }
        return $sections;
    }
}
