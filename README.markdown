TinyDb
======
A minimalist set of tools for creating database-driven PHP applications.

Introduction
------------
> TinyDb was created out of a frustration for an easy way to write database-driven apps in PHP. It seemed
> like 90% of the code I was writing was just grabbing some data from a database and populating a model.
> Frameworks like FuelPHP or CodeIgniter promised a better way of doing it, but the ORM system required
> lengthy configuration. Their database access class was fantastic, but also tied up in a large framework
> with a lot of overhead.
>
> TinyDb does very little in a very simple manner. It doesn't do everything you could want (foreign key
> relationships, for example, are deliberately omitted), but it provides a simple and clean interface for
> adding these features to your models. It provides a simple method for generating SQL queries, and for
> fetching sets of models from the database.

Requirements
------------
 * PHP &ge; 5.3.5
 * MDB2

Connecting
----------
Before you can connect to the database, you'll need to give TinyDb a database connection. You can do
this with the `\TinyDb\Db::set()` method. It takes either one or two paramaters:

 * `$write` - a read/write connection to the database
 * `$read` - a read-only connection to the database (optional)

Both of the paramaters should either be an MDB2 connection, or a MDB2 connection string.

Using $read is a super simple way to load balance - have one read/write master MySQL server, and several
read-only slaves. Then just pick a read-only server at random when you initialize TinyDb. It may not scale
to infinity, but it scales easily and quickly!

SQL
===
TinyDb makes SQL easy. Just use the `\TinyDb\Sql` object to write queries like you normally would, except
with PHP functions. The easiest way to explain is to show you:

    // SELECTing...
    \TinyDb\Sql::create()->select('*')->from('users')->join('`cats` USING (`catID`)', 'LEFT')->limit(5, 15);

    // INSERTing...
    \TinyDb\Sql::create()->insert()->into('cats', ['breedID', 'color'])->values(12, 'blue');
    \TinyDb\Sql::create()->insert()->into('users')->set('breedID = ?', 12)->set('color = ?', 'blue');

    // UPDATEing...
    \TinyDb\Sql::create()->update('users')->set('breedID = ?', 11)->where('catID = ?', 4)->limit(1);

    // and even DELETEing!
    \TinyDb\Sql::create()->delete()->from('users')->where('breedID = ?', 5);

Other than a few minor differences (notice the format of `join()`), it's exactly what you'd expect. And every
method returns the Sql object, so you can chain them together. (It's definitely mutable, though! Be careful
to always clone an object if you don't want the main object changed).

Notice those question marks? That's right, TinySql will automatically prepare your query for you, too! (One
quick note about this: it's not possible to use the auto-prepare feature if you use VALUES() notation for
inserts! I strongly recommend SET notation.)

ORM
===
ORM-enabled classes are created by extending from `\TinyDb\Orm`. All TinyOrm classes need to create two
static fields:

 * `table_name` - the name of the table
 * `primary_key` - the primary key of the table (either a string, or an array if it's a composite key)

The remainder of the ORM is handled by creating protected instance variables with the names of database
fields. The type of the field will be automatically inferred from the database structure. Remember,
that's _protected_ instance variables. Marking them protected causes get and set requests to go through
TinyOrm's magic PHP getters and setters.

Creating Objects
----------------
To create an object, just call the static function `create()` on that object's type. It takes an
associative array of keys and values which correspond to fields in the database and returns the
created object. You'll probably want to override it in your models for simplicity, i.e.:

    public static function create($name, $breed, $color)
    {
        return parent::create([
            'name' => $name,
            'breedID' => $breed->breedID,
            'color' => $color
        ]);
    }

(If you do override it, make sure to __return__ the result of `parent::create`!)

Loading Objects
---------------
There are several ways to load objects in TinyOrm. All of them involve the constructor.

 * You can use an empty constructor. The object will be uninitialized. You can fill it later with `fill_data()`
 * You can pass a primative, and TinyOrm will load the object with the primary key which matches
 * If the primary key is an array, you can pass an array of primatives
 * You can pass an associative array, which TinyOrm turns into a WHERE statement

Updating Objects
----------------
Whenever you've made some updates to an object and want to commit them to the database, call the `update()`
function and TinyOrm will take care of it.

Optionally, don't call `update()` and TinyOrm will update it for you in its destructor. This generally makes
tracking down update bugs more difficult, however, so it's not suggested.

Deleting Objects
----------------
To delete an object just call the `delete()` function on it. (The object will prevent you from accessing
anything after it's deleted, however any copies you might have won't, so be careful.)

Validations
-----------
TinyOrm supports validating your fields (and your properties, which we'll get to in a moment). You can define
a validation by defining a function: `__validate_fieldName($val)` (where fieldName is the name of the field to
validate, obviously.)

TinyOrm even includes some built-in validations! To use them, use a string for `__validate_fieldName` instead of
a function. The value should be one of the following:

 * string/str
 * integer/int
 * boolean/bool
 * email
 * phone
 * ssn
 * date
 * time
 * datetime

Magic Fields
------------
TinyOrm supports a few magic fields:

 * `created_at` - the date the object was first created
 * `modified_at` - the date the object was last modified

These fields will be automatically filled if a field of the type `datetime` exists in their corresponding database.

Properties
----------
TinyOrm supports properties. To create one, define one or both of the magic methods (replacing
propertyName with the name of the property):

 * `__get_propertyName()`
 * `__set_propertyName($val)`

This is quite useful for foreign keys. For example, imagine a blog post, which has an associated user.
The database collects this relationship with the field `userID`. We can create a lazy-loaded user as such:

    private $_user = NULL;
    protected function __get_user()
    {
        if (!isset($this->_user)) {
            $this->_user = new Models\User($this->userID);
        }

        return $this->_user;
    }

    protected function __set_user(Models\User $val)
    {
        $this->_user = NULL;
        $this->userID = $val->userID;
    }

That's it - now we can call, for example, `$blogpost->user->username` and it works exactly as you'd expect.
Note that if we hadn't defined a `__set_user()` method, attempts to change the user would fail.

Collections
===========
Collections are just what they sound like: collections of things. TinyOrm classes, to be specific. Creating
a collection is easy, just pass it the name of a Model which inherits from `\TinyDb\Orm` and a Sql query.
The collection will be populated with all the models matching the query!

You'll find that it's often useful to extend a collection and add your own constructor and methods. For
example:

    class Permissions extends \TinyDb\Collection
    {
        protected $user = NULL;
        public function __construct($user)
        {
            $this->user = $user;
            parent::__construct('\StudentRnd\Models\AccessControl\Permission',
                \TinyDb\Sql::create()
                        ->select()
                        ->from(AccessControl\Permission::$table_name)
                        ->join('`users_permissions` USING (`permissionID`)')
                        ->where('`userID` = ?', $user->userID));
        }

        public function has_permission($permission)
        {
            return $this->contains(function($model) use($permission) {
                return $model->name === $permission;
            });
        }

        public function grant_permission($permission)
        {
            $this->data[] = Mappings\UserPermission::create($this->user, $permission);
        }

        public function remove_permission($permission)
        {
            $mapping = new Mappings\UserPermission($this->user, $permission);
            $this->remove($mapping);
            $mapping->delete();
        }
    }

Collections have several useful methods:

`each($lambda)`
---------------
`each` performs an action on each model in the collection. It takes one paramater, a function to perform which
itself takes one paramater - the model to perform the action on. The return values for `$lambda` are collected
into an array, which this function returns.

`find($lambda)`
---------------
`find` builds a subcollection of models matching a given query. It takes one paramater - again, a function which
is executed on each model (passed to the callback as its first paramater). If that function returns `TRUE`, the
model is included. Because it returns a `TinyDb\Collection`, you can chain calls off this.

`find_one($lambda)`
-------------------
A shortcut for `find($lambda)[0]`. Returns an instance of `TinyDb\Orm`, or `NULL` if none is found.

`filter($lambda)`
-----------------
The mutable version of `find($lambda)`. Updates and returns the current collection. You can chain calls off this.

`remove($model)`
----------------
A shortcut for `find(function($m) use($model){return (!$model->equals($m));})`. Removes all instances of the model
from the current collection.

`contains($lambda)`
-------------------
A shortcut for `count(find($lambda)) > 0`. Returns `TRUE` if the collection contains at least one matching model,
otherwise `FALSE`.