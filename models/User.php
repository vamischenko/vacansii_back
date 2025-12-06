<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use yii\behaviors\TimestampBehavior;

/**
 * Модель пользователя
 *
 * Представляет пользователя системы с безопасной аутентификацией.
 * Использует хеширование паролей и генерацию токенов доступа.
 *
 * @property int $id Уникальный идентификатор пользователя
 * @property string $username Имя пользователя
 * @property string $email Email пользователя
 * @property string $password_hash Хешированный пароль
 * @property string $auth_key Ключ для автоматической авторизации
 * @property string $access_token Токен доступа для API
 * @property int $status Статус пользователя (10-active, 0-deleted)
 * @property int $created_at Время создания записи (Unix timestamp)
 * @property int $updated_at Время последнего обновления (Unix timestamp)
 */
class User extends ActiveRecord implements IdentityInterface
{
    /**
     * Константы статусов пользователей
     */
    public const STATUS_DELETED = 0;
    public const STATUS_ACTIVE = 10;

    /**
     * Временное поле для установки пароля
     * Не хранится в БД, используется только для валидации и хеширования
     * @var string
     */
    public $password;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%user}}';
    }

    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            ['status', 'default', 'value' => self::STATUS_ACTIVE],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_DELETED]],

            [['username', 'email'], 'required'],
            [['username', 'email'], 'string', 'max' => 255],
            ['email', 'email'],
            ['username', 'unique'],
            ['email', 'unique'],

            ['password', 'string', 'min' => 6],
            ['password', 'required', 'on' => 'create'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'username' => 'Имя пользователя',
            'email' => 'Email',
            'password_hash' => 'Хеш пароля',
            'password' => 'Пароль',
            'auth_key' => 'Ключ авторизации',
            'access_token' => 'Токен доступа',
            'status' => 'Статус',
            'created_at' => 'Создан',
            'updated_at' => 'Обновлен',
        ];
    }

    /**
     * Поля для API ответов
     */
    public function fields()
    {
        return [
            'id',
            'username',
            'email',
            'status',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Находит пользователя по ID
     *
     * @param string|int $id ID пользователя
     * @return static|null
     */
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * {@inheritdoc}
     *
     * Находит пользователя по токену доступа
     *
     * @param string $token Токен доступа
     * @param mixed $type Тип токена (не используется)
     * @return static|null
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        return static::findOne(['access_token' => $token, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Находит пользователя по имени пользователя
     *
     * @param string $username Имя пользователя
     * @return static|null
     */
    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Находит пользователя по email
     *
     * @param string $email Email пользователя
     * @return static|null
     */
    public static function findByEmail($email)
    {
        return static::findOne(['email' => $email, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * {@inheritdoc}
     *
     * Возвращает ID пользователя
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     *
     * Возвращает ключ авторизации
     *
     * @return string
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * {@inheritdoc}
     *
     * Проверяет ключ авторизации
     *
     * @param string $authKey Ключ для проверки
     * @return bool
     */
    public function validateAuthKey($authKey)
    {
        return $this->auth_key === $authKey;
    }

    /**
     * Проверяет пароль пользователя
     *
     * @param string $password Пароль для проверки
     * @return bool True если пароль верный, false иначе
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Устанавливает пароль пользователя
     *
     * Создает безопасный хеш пароля
     *
     * @param string $password Пароль в открытом виде
     * @throws \yii\base\Exception
     */
    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Генерирует ключ авторизации
     *
     * Используется для функции "запомнить меня"
     *
     * @throws \yii\base\Exception
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString(32);
    }

    /**
     * Генерирует токен доступа для API
     *
     * @throws \yii\base\Exception
     */
    public function generateAccessToken()
    {
        $this->access_token = Yii::$app->security->generateRandomString(64);
    }

    /**
     * {@inheritdoc}
     *
     * Выполняется перед сохранением модели
     * Автоматически хеширует пароль при создании или изменении
     *
     * @param bool $insert Флаг создания новой записи
     * @return bool
     * @throws \yii\base\Exception
     */
    public function beforeSave($insert)
    {
        if (!parent::beforeSave($insert)) {
            return false;
        }

        // Если установлен пароль - хешируем его
        if (!empty($this->password)) {
            $this->setPassword($this->password);
        }

        // При создании генерируем auth_key и access_token если они не заданы
        if ($insert) {
            if (empty($this->auth_key)) {
                $this->generateAuthKey();
            }
            if (empty($this->access_token)) {
                $this->generateAccessToken();
            }
        }

        return true;
    }
}
