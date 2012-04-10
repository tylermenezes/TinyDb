TinyDb
======
A minimalist set of tools for creating database-driven applications

Introduction
------------
TinyDb was created out of a frustration for an easy way to write database-driven apps in PHP. It seemed
like 90% of the code I was writing was just grabbing some data from a database and populating a model.
Frameworks like FuelPHP or CodeIgniter promised a better way of doing it, but the ORM system required
lengthy configuration. Their database access class was fantastic, but also tied up in a large framework
with a lot of overhead.

TinyDb does very little in a very simple manner. It doesn't do everything you could want (foreign key
relationships, for example, are deliberately omitted), but it provides a simple and clean interface for
adding these features to your models. It provides a simple method for generating SQL queries, and for
fetching sets of models from the database.

Requirements
------------
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

    \TinyDb\Sql::create()->select('*')->from('users')->join('`cats` USING (`catID`)', 'LEFT')->where('`created_at` < ?', time())->limit(1);

Other than a few minor differences (notice the format of `join()`), it's exactly what you'd expect. And every
method returns the Sql object, so you can chain them together. (It's definitely mutable, though! Be careful
to always clone an object if you don't want the main object changed).

Notice those question marks? That's right, TinySql will automatically prepare your query for you, too!

ORM
===
ORM-enabled classes are created by extending from `\TinyDb\Orm`. All TinyOrm classes need to create two
static fields:

 * `table_name` - the name of the table
 * `primary_key` - the primary key of the table (either a string, or an array)

The remainder of the ORM is handled by creating protected instance variables with the names of database
fields. The type of the field will be automatically inferred from the database structure. Remember,
that's _protected_ instance variables. Marking them protected causes get and set requests to go through
TinyOrm's magic PHP getters and setters.

Creating Objects
----------------
To create an object, just call the static function `create()` on that object's type. It takes an
associative array of keys and values which correspond to fields in the database. All the fields need to
be set, even if that means setting them to NULL.

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

Deleting Objects
----------------
To delete an object just call the `delete()` function on it.

Validations
-----------
TinyOrm supports validating your fields (and your properties, which we'll get to in a moment). You can define
a validation by defining a function: `__validate_fieldName($val)` (where fieldName is the name of the field to
validate, obviously.)

TinyOrm even includes some built-in validations! To use them, use a string for `__validate_fieldName` instead of
a function. The value should be one of the following:

 * string/str
 * integer/int
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

    private $user = NULL;
    protected function __get_user()
    {
        if (!isset($this->user)) {
            $this->user = new Models\User($this->userID);
        }

        return $this->user;
    }

    protected function __set_user(Models\User $val)
    {
        $this->user = NULL;
        $this->userID = $val->userID;
    }

That's it - now we can call, for example, `$blogpost->user->username` and it works exactly as you'd expect.
Note that if we hadn't defined a __set_user method, attempts to change the user would fail.

Collections
===========
Collections are just what they sound like: collections of things. TinyOrm classes, to be specific. Creating
a collection is easy, just pass it the name of a Model which inherits from `\TinyDb\Orm` and a Sql query.
The collection will be populated with all the models matching the query!