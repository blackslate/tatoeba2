<?php
namespace App\Model;

use App\Lib\LanguagesLib;

class Search {
    use \Cake\Datasource\ModelAwareTrait;

    private $query;
    private $lang;
    private $ownerId;
    private $hasOwner;
    private $correctness;
    private $hasAudio;
    private $sort;
    private $sortReversed;

    private $translationFilter;
    private $translationFilters = [];

    private function getTranslationFiltersAsSphinx() {
        $transFilter = [];
        $sphinxMap = [
            'hasAudio' => function($v) { return 't.a='.(int)$v; },
        ];
        foreach ($this->getTranslationFilters() as $filter => $value) {
            $transFilter[] = $sphinxMap[$filter]($value);
        }
        return $transFilter;
    }

    private function parseBoolean($value, &$variable) {
        if (in_array($value, ['yes', 'no'])) {
            $variable = $value == 'yes';
        }
    }

    private function orderby($expr, $order) {
        return $expr . ($order ? ' ASC' : ' DESC');
    }

    private function asSphinxIndex($lang) {
        if ($lang) {
            return [$lang. '_main_index', $lang . '_delta_index'];
        } else {
            return ['und_index'];
        }
    }

    public function asSphinx() {
        $sphinx = [
            'index' => $this->asSphinxIndex($this->lang),
        ];
        if (!is_null($this->query)) {
            $sphinx['query'] = $this->query;
        }
        if ($this->ownerId) {
            $sphinx['filter'][] = ['user_id', $this->ownerId];
        }
        if (!is_null($this->hasOwner)) {
            $sphinx['filter'][] = ['user_id', 0, !$this->hasOwner];
        }
        if (!is_null($this->correctness)) {
            // See the indexation SQL request for the value 127
            $sphinx['filter'][] = ['ucorrectness', 127, !$this->correctness];
        }
        if (!is_null($this->hasAudio)) {
            $sphinx['filter'][] = array('has_audio', $this->hasAudio);
        }
        if (!is_null($this->translationFilter)) {
            $transFilter = $this->getTranslationFiltersAsSphinx();
            if (empty($transFilter)) {
                $transFilter = [1];
            }
            $filter = implode(' & ', $transFilter);
            $sphinx['select'] = "*, ANY($filter FOR t IN trans) as filter";

            $filter = $this->translationFilter == 'limit' ? 1 : 0;
            $sphinx['filter'][] = ['filter', $filter];
        }
        if ($this->sort) {
            if ($this->sort == 'random') {
                $sortOrder = '@random';
            } elseif (empty($this->query)) {
                // When the query is empty, Manticore does not perform any
                // ranking, so we need to rely on ordering instead
                if ($this->sort == 'created' || $this->sort == 'modified') {
                    $sortOrder = $this->orderby($this->sort, $this->sortReversed);
                } else {
                    $sortOrder = $this->orderby('text_len', !$this->sortReversed);
                }
            } else {
                // When there are keywords, Manticore will perform ranking
                $sortOrder = $this->orderby('@rank', $this->sortReversed);
                if ($this->sort == 'words') {
                    $rankingExpr = '-text_len';
                } elseif ($this->sort == 'relevance') {
                    $rankingExpr = '-text_len+top(lcs+exact_order*100)*100';
                } elseif ($this->sort == 'created' || $this->sort == 'modified') {
                    $rankingExpr = $this->sort;
                }
            }
            $sphinx['matchMode'] = SPH_MATCH_EXTENDED2;
            $sphinx['sortMode'] = [SPH_SORT_EXTENDED => $sortOrder];
            if (isset($rankingExpr)) {
                $sphinx['rankingMode'] = [SPH_RANK_EXPR => $rankingExpr];
            }
        }
        return $sphinx;
    }

    public function filterByQuery($query) {
        $this->query = $query;
    }

    public function filterByLanguage($lang) {
        if (LanguagesLib::languageExists($lang)) {
            $this->lang = $lang;
        }
    }

    public function filterByOwnerName($owner) {
        if (!empty($owner)) {
            $this->loadModel('Users');
            $result = $this->Users->findByUsername($owner, ['fields' => ['id']])->first();
            if ($result) {
                $this->ownerId = $result->id;
            } else {
                return false;
            }
        }
        return true;
    }

    public function filterByOwnership($hasOwner) {
        $this->parseBoolean($hasOwner, $this->hasOwner);
    }

    public function sort($sort) {
        if (in_array($sort, ['relevance', 'words', 'created', 'modified', 'random'])) {
            $this->sort = $sort;
        }
    }

    public function reverseSort($reversed) {
        $this->sortReversed = $reversed;
    }

    public function filterByCorrectness($correctness) {
        $this->parseBoolean($correctness, $this->correctness);
    }

    public function filterByAudio($hasAudio) {
        $this->parseBoolean($hasAudio, $this->hasAudio);
    }

    public function filterByTranslation($filter) {
        if (in_array($filter, ['exclude', 'limit'])) {
            $this->translationFilter = $filter;
        }
    }

    public function filterByTranslationAudio($filter) {
        $this->parseBoolean($filter, $this->translationFilters['hasAudio']);
    }

    public function getTranslationFilters() {
        return array_filter(
            $this->translationFilters,
            function ($v) { return !is_null($v); }
        );
    }
}
