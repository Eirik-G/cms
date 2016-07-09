<?php
/**
 * @link      http://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   http://craftcms.com/license
 */

namespace craft\app\services;

use Craft;
use craft\app\db\Query;
use craft\app\errors\TagGroupNotFoundException;
use craft\app\errors\TagNotFoundException;
use craft\app\events\TagEvent;
use craft\app\elements\Tag;
use craft\app\models\TagGroup as TagGroupModel;
use craft\app\records\Tag as TagRecord;
use craft\app\records\TagGroup as TagGroupRecord;
use yii\base\Component;

/**
 * Class Tags service.
 *
 * An instance of the Tags service is globally accessible in Craft via [[Application::tags `Craft::$app->getTags()`]].
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  3.0
 */
class Tags extends Component
{
    // Constants
    // =========================================================================

    /**
     * @event TagEvent The event that is triggered before a tag is saved.
     *
     * You may set [[TagEvent::isValid]] to `false` to prevent the tag from getting saved.
     */
    const EVENT_BEFORE_SAVE_TAG = 'beforeSaveTag';

    /**
     * @event TagEvent The event that is triggered after a tag is saved.
     */
    const EVENT_AFTER_SAVE_TAG = 'afterSaveTag';

    // Properties
    // =========================================================================

    /**
     * @var
     */
    private $_allTagGroupIds;

    /**
     * @var
     */
    private $_tagGroupsById;

    /**
     * @var bool
     */
    private $_fetchedAllTagGroups = false;

    // Public Methods
    // =========================================================================

    // Tag groups
    // -------------------------------------------------------------------------

    /**
     * Returns all of the group IDs.
     *
     * @return array
     */
    public function getAllTagGroupIds()
    {
        if (!isset($this->_allTagGroupIds)) {
            if ($this->_fetchedAllTagGroups) {
                $this->_allTagGroupIds = array_keys($this->_tagGroupsById);
            } else {
                $this->_allTagGroupIds = (new Query())
                    ->select('id')
                    ->from('{{%taggroups}}')
                    ->column();
            }
        }

        return $this->_allTagGroupIds;
    }

    /**
     * Returns all tag groups.
     *
     * @param string|null $indexBy
     *
     * @return array
     */
    public function getAllTagGroups($indexBy = null)
    {
        if (!$this->_fetchedAllTagGroups) {
            $this->_tagGroupsById = TagGroupRecord::find()
                ->orderBy('name')
                ->indexBy('id')
                ->all();

            foreach ($this->_tagGroupsById as $key => $value) {
                $this->_tagGroupsById[$key] = TagGroupModel::create($value);
            }

            $this->_fetchedAllTagGroups = true;
        }

        if ($indexBy == 'id') {
            return $this->_tagGroupsById;
        } else if (!$indexBy) {
            return array_values($this->_tagGroupsById);
        } else {
            $tagGroups = [];

            foreach ($this->_tagGroupsById as $group) {
                $tagGroups[$group->$indexBy] = $group;
            }

            return $tagGroups;
        }
    }

    /**
     * Gets the total number of tag groups.
     *
     * @return integer
     */
    public function getTotalTagGroups()
    {
        return count($this->getAllTagGroupIds());
    }

    /**
     * Returns a group by its ID.
     *
     * @param integer $groupId
     *
     * @return TagGroupModel|null
     */
    public function getTagGroupById($groupId)
    {
        if (!isset($this->_tagGroupsById) || !array_key_exists($groupId,
                $this->_tagGroupsById)
        ) {
            $groupRecord = TagGroupRecord::findOne($groupId);

            if ($groupRecord) {
                $this->_tagGroupsById[$groupId] = TagGroupModel::create($groupRecord);
            } else {
                $this->_tagGroupsById[$groupId] = null;
            }
        }

        return $this->_tagGroupsById[$groupId];
    }

    /**
     * Gets a group by its handle.
     *
     * @param string $groupHandle
     *
     * @return TagGroupModel|null
     */
    public function getTagGroupByHandle($groupHandle)
    {
        $groupRecord = TagGroupRecord::findOne([
            'handle' => $groupHandle
        ]);

        if ($groupRecord) {
            return TagGroupModel::create($groupRecord);
        }
    }

    /**
     * Saves a tag group.
     *
     * @param TagGroupModel $tagGroup
     *
     * @return boolean Whether the tag group was saved successfully
     * @throws TagGroupNotFoundException if $tagGroup->id is invalid
     * @throws \Exception if reasons
     */
    public function saveTagGroup(TagGroupModel $tagGroup)
    {
        if ($tagGroup->id) {
            $tagGroupRecord = TagGroupRecord::findOne($tagGroup->id);

            if (!$tagGroupRecord) {
                throw new TagGroupNotFoundException("No tag group exists with the ID '{$tagGroup->id}'");
            }

            $oldTagGroup = TagGroupModel::create($tagGroupRecord);
            $isNewTagGroup = false;
        } else {
            $tagGroupRecord = new TagGroupRecord();
            $isNewTagGroup = true;
        }

        $tagGroupRecord->name = $tagGroup->name;
        $tagGroupRecord->handle = $tagGroup->handle;

        $tagGroupRecord->validate();
        $tagGroup->addErrors($tagGroupRecord->getErrors());

        if (!$tagGroup->hasErrors()) {
            $transaction = Craft::$app->getDb()->beginTransaction();
            try {
                // Is there a new field layout?
                $fieldLayout = $tagGroup->getFieldLayout();
                if (!$fieldLayout->id) {
                    // Delete the old one
                    if (!$isNewTagGroup && $oldTagGroup->fieldLayoutId) {
                        Craft::$app->getFields()->deleteLayoutById($oldTagGroup->fieldLayoutId);
                    }

                    // Save the new one
                    Craft::$app->getFields()->saveLayout($fieldLayout);

                    // Update the tag group record/model with the new layout ID
                    $tagGroup->fieldLayoutId = $fieldLayout->id;
                    $tagGroupRecord->fieldLayoutId = $fieldLayout->id;
                }

                // Save it!
                $tagGroupRecord->save(false);

                // Now that we have a tag group ID, save it on the model
                if (!$tagGroup->id) {
                    $tagGroup->id = $tagGroupRecord->id;
                }

                // Might as well update our cache of the tag group while we have it.
                $this->_tagGroupsById[$tagGroup->id] = $tagGroup;

                $transaction->commit();
            } catch (\Exception $e) {
                $transaction->rollBack();

                throw $e;
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Deletes a tag group by its ID.
     *
     * @param integer $tagGroupId
     *
     * @return boolean Whether the tag group was deleted successfully
     * @throws \Exception if reasons
     */
    public function deleteTagGroupById($tagGroupId)
    {
        if (!$tagGroupId) {
            return false;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();
        try {
            // Delete the field layout
            $fieldLayoutId = (new Query())
                ->select('fieldLayoutId')
                ->from('{{%taggroups}}')
                ->where(['id' => $tagGroupId])
                ->scalar();

            if ($fieldLayoutId) {
                Craft::$app->getFields()->deleteLayoutById($fieldLayoutId);
            }

            // Grab the tag ids so we can clean the elements table.
            $tagIds = (new Query())
                ->select('id')
                ->from('{{%tags}}')
                ->where(['groupId' => $tagGroupId])
                ->column();

            Craft::$app->getElements()->deleteElementById($tagIds);

            $affectedRows = Craft::$app->getDb()->createCommand()
                ->delete('{{%taggroups}}', ['id' => $tagGroupId])
                ->execute();

            $transaction->commit();

            return (bool)$affectedRows;
        } catch (\Exception $e) {
            $transaction->rollBack();

            throw $e;
        }
    }

    // Tags
    // -------------------------------------------------------------------------

    /**
     * Returns a tag by its ID.
     *
     * @param integer     $tagId
     * @param string|null $localeId
     *
     * @return Tag|null
     */
    public function getTagById($tagId, $localeId)
    {
        return Craft::$app->getElements()->getElementById($tagId, Tag::className(), $localeId);
    }

    /**
     * Saves a tag.
     *
     * @param Tag $tag
     *
     * @return boolean Whether the tag was saved successfully
     * @throws TagNotFoundException if $tag->id is invalid
     * @throws \Exception if reasons
     */
    public function saveTag(Tag $tag)
    {
        $isNewTag = !$tag->id;

        // Tag data
        if (!$isNewTag) {
            $tagRecord = TagRecord::findOne($tag->id);

            if (!$tagRecord) {
                throw new TagNotFoundException("No tag exists with the ID '{$tag->id}'");
            }
        } else {
            $tagRecord = new TagRecord();
        }

        $tagRecord->groupId = $tag->groupId;

        $tagRecord->validate();
        $tag->addErrors($tagRecord->getErrors());

        if ($tag->hasErrors()) {
            return false;
        }

        $transaction = Craft::$app->getDb()->beginTransaction();

        try {
            // Fire a 'beforeSaveTag' event
            $event = new TagEvent([
                'tag' => $tag
            ]);

            $this->trigger(static::EVENT_BEFORE_SAVE_TAG, $event);

            // Is the event giving us the go-ahead?
            if ($event->isValid) {
                $success = Craft::$app->getElements()->saveElement($tag, false);

                // If it didn't work, rollback the transaction in case something changed in onBeforeSaveTag
                if (!$success) {
                    $transaction->rollBack();

                    return false;
                }

                // Now that we have an element ID, save it on the other stuff
                if ($isNewTag) {
                    $tagRecord->id = $tag->id;
                }

                $tagRecord->save(false);
            } else {
                $success = false;
            }

            // Commit the transaction regardless of whether we saved the tag, in case something changed
            // in onBeforeSaveTag
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();

            throw $e;
        }

        if ($success) {
            // Fire an 'afterSaveTag' event
            $this->trigger(static::EVENT_AFTER_SAVE_TAG, new TagEvent([
                'tag' => $tag
            ]));
        }

        return $success;
    }
}
