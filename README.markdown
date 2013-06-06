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

Both of the paramaters should either be an MDB2 connection, a MDB2 connection string, or an array of one of the former. If an array is
used, one will be randomly selected at runtime.

Using $read is a super simple way to load balance - have one read/write master MySQL server, and several
read-only slaves. Then just pick a read-only server at random when you initialize TinyDb.

Query
===
TinyDb makes SQL easy. Just use the `\TinyDb\Query` object to write queries like you normally would, except
with PHP functions. The easiest way to explain is with code:

    // SELECTing...
    \TinyDb\Query::create()->select('*')->from('users')->join('`cats` USING (`catID`)', 'LEFT')->limit(5, 15);

    // INSERTing...
    \TinyDb\Query::create()->insert()->into('cats', ['breedID', 'color'])->values(12, 'blue');
    \TinyDb\Query::create()->insert()->into('users')->set('breedID = ?', 12)->set('color = ?', 'blue');

    // UPDATEing...
    \TinyDb\Query::create()->update('users')->set('breedID = ?', 11)->where('catID = ?', 4)->limit(1);

    // and even DELETEing!
    \TinyDb\Query::create()->delete()->from('users')->where('breedID = ?', 5);

Other than a few minor differences (notice the format of `join()`), it's exactly what you'd expect. And every
method returns the Query object, so you can chain them together. (It's definitely mutable, though! Be careful
to always clone an object if you don't want the main object changed).

Notice those question marks? That's right, TinyDb will automatically prepare your query for you, too! (One
quick note about this: it's not possible to use the auto-prepare feature if you use VALUES() notation for
inserts! SET notation is strongly recommended.)

To execute your query, terminate the chain with `execute()`. If the query is clearly limited to a [1x1] result (e.g. a `SELECT COUNT(*)
FROM ...` query), execute() will return only that result. Likewise, if you run `->limit(1)`, it will return only the row, instead of a
1-length row collection. This behavior is usually helpful, but can be disabled by passing `true` to `execute()`.

ORM
===
ORM-enabled classes are created by extending from `\TinyDb\Orm`. All TinyOrm classes need to create one static field:

 * `table_name` - the name of the table

The remainder of the ORM is handled by creating instance variables with the names of database fields. The type of the field will be
automatically inferred from the database structure.

Creating Objects
----------------
To create an object, just create a new object of that type. Its constructor takes an associative array of keys and values which correspond
to fields in the database and returns the created object. i.e.

    $user = new Models\User([
        'username' => 'tylermenezes',
        'age' => 20
    ]);

Loading Objects
---------------
There are several ways to load objects in TinyOrm.

 * Pass the value of a primary key into the static `::one()` function: `Models\User::one('tylermenezes')`. For composite keys, use an
   array, or pass the values params-wise.
 * Pass the value of a primary key into the static `::find()` function, which serves as a shortcut to `::one()` when used like this.
 * Use the static `::find()` method with no parameters to build up a query chain, terminated with `->one()` or `->all()`.

 The latter is the most flexible. For example, to get an array containing all users under 18, you might write a query like so:

    $under_18_users = Models\User::find()->where('age < ?', 20)->all();

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

 * `get_propertyName()`
 * `set_propertyName($val)`

Foreign Keys
------------

You can mark a key as foreign in a docblock immediately preceding it:

    /**
     * The user's company
     * @foreign \Foo\Models\Company company
     */
    public $companyID;

Attempting to access `$user->company`, in the above example, would automatically load the corresponding `Company` with that `companyID`.
This only works for foreign relations of a single primary key, and the exposed param is always public. For greater control, use properties.
