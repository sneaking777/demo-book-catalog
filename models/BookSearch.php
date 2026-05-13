<?php

declare(strict_types=1);

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

/**
 * Поисковая модель для {@see Book}.
 *
 * Используется в `BookController::actionIndex()` для фильтрации
 * списка книг по значениям формы поиска в GridView.
 *
 * Наследуется от Book, чтобы автоматически получить набор атрибутов;
 * собственные правила валидации в {@see rules()} помечают поля как
 * `safe` (можно массово присваивать через `load()`), а строгие правила
 * родителя игнорируются за счёт переопределения {@see scenarios()}.
 *
 * @package app\models
 *
 * @extends Book
 */
class BookSearch extends Book
{
    /**
     * Возвращает правила валидации для атрибутов формы поиска.
     *
     * @return array<int, array<int|string, mixed>>
     */
    public function rules(): array
    {
        return [
            [['id', 'year', 'created_at', 'updated_at'], 'integer'],
            [['title', 'description', 'isbn', 'cover_image'], 'safe'],
        ];
    }

    /**
     * Сбрасывает сценарии родительской модели, чтобы поля можно было
     * массово присваивать без срабатывания строгих правил Book.
     *
     * @return array<string, array<int, string>>
     */
    public function scenarios(): array
    {
        return Model::scenarios();
    }

    /**
     * Строит data provider для GridView с применением условий фильтрации
     * из переданных параметров.
     *
     * @param array<string, mixed> $params Параметры запроса (обычно $_GET).
     * @param string|null $formName Имя формы для метода `load()` или null для значения по умолчанию.
     *
     * @return ActiveDataProvider
     */
    public function search(array $params, ?string $formName = null): ActiveDataProvider
    {
        $query = Book::find()->with('authors');

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
        ]);

        $this->load($params, $formName);

        if (!$this->validate()) {
            return $dataProvider;
        }

        $query->andFilterWhere([
            'id' => $this->id,
            'year' => $this->year,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ]);

        $query->andFilterWhere(['like', 'title', $this->title])
            ->andFilterWhere(['like', 'description', $this->description])
            ->andFilterWhere(['like', 'isbn', $this->isbn])
            ->andFilterWhere(['like', 'cover_image', $this->cover_image]);

        return $dataProvider;
    }
}