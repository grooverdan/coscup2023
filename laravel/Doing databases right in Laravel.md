# Doing Databases Right in Laravel

Daniel Black
- MariaDB Expert
- (not PHP or Laravel expert)
- Please interupt if say silly on the Laravel side.

Covering:

* Datatypes
* Structures
* Queries
* Indicies/Indexs

This is the order you _design_ the data side of your application.

If you are _debugging_ problems, reverse the order as that will
yield quicker less intrusive fixes.

# Choices

ORM:

- RedBeanPHP
- Doctrine
- Eloquent - ended development

RAW:
- Illuminate\Support\Facades\DB


# Datatypes

[MariaDB types](https://mariadb.com/kb/en/data-types/) (extract):

* INT/BIT
* FLOAT/DOUBLE
* DECIMAL

* [VAR]CHAR
* BLOB/TEXT
* ENUM

* DATE
* TIMESTAMP
* YEAR

* JSON

Doctrine 2 Annotation [types](https://www.doctrine-project.org/projects/doctrine-dbal/en/current/reference/types.html#types)
```
<?php
// src/Product.php

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="products")
 */
class Product
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue
     */
    private int|null $id = null;
    /**
     * @ORM\Column(type="string")
     */
    private string $name;

    // .. (other code)
}
```


[RedBeanPHP](https://redbeanphp.com/index.php?p=/crud)
```
<?php
    $book = R::dispense( 'book' );

    $book->title = 'Learn to Program';
    $book->rating = 10;
    $book->sold = '2023-07-29'; // Date
    $book->cost = '12.37'; // Decimal
    $book->weight = 0.53; // Double
```


[RedBeanPHP](https://redbeanphp.com/index.php?p=/database#column_functions)
```
<?php
    R::bindFunc( 'read', 'location.point', 'asText' );
    R::bindFunc( 'write', 'location.point', 'GeomFromText' );

    $location = R::dispense( 'location' );
    $location->point = 'POINT(14 6)';

    //inserts using GeomFromText() function
    R::store( $location );

    //to unbind a function, pass NULL:
    R::bindFunc( 'read', 'location.point', NULL );
```

## Other MariaDB Types

* INET4
* INET6

[Doctrine custom types](https://www.doctrine-project.org/projects/doctrine-dbal/en/latest/reference/types.html#custom-mapping-types)
```
<?php
namespace My\Project\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;

/**
 * My custom datatype.
 */
class Inet6Type extends Type
{
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'MariaDB_INET6';
    }
}

Type::addType('inet6', 'My\Project\Types\Inet6Type');
$conn->getDatabasePlatform()->registerDoctrineTypeMapping('MariaDB_INET6', 'inet6');
```

[RedBeanPHP is possible](https://redbeanphp.com/index.php?p=/uuids), extend MySQL class.

# Table Structures

## Less Structured Data

Arrays and JSON types.

Good usage:
* Fetch and set only

Bad Usage:
* Search all data for element in array - use relations
* Search for JSON with explicit member and value


## Relations

### One to Many

[RedBeanPHP]https://redbeanphp.com/index.php?p=/one_to_many)
```
<?php
    $shop = R::dispense( 'shop' );
    $shop->name = 'Antiques';
```

To add products to the shop, add beans to the ownProductList property, like this:

```
<?php
    $vase = R::dispense( 'product' );
    $vase->price = 25;
    $shop->ownProductList[] = $vase
    R::store( $shop );
```

[Doctrine](https://www.doctrine-project.org/projects/doctrine-orm/en/latest/tutorials/working-with-indexed-associations.html#mapping-indexed-associations):
```
<?php
namespace Doctrine\Tests\Models\StockExchange;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[Entity]
#[Table(name: 'exchange_markets')]
class Market
{
    #[Id, Column(type: 'integer'), GeneratedValue]
    private int|null $id = null;

    #[Column(type: 'string')]
    private string $name;

    /** @var Collection<string, Stock> */
    #[OneToMany(targetEntity: Stock::class, mappedBy: 'market', indexBy: 'symbol')]
    private Collection $stocks;
```

### Many to Many

[RedBeanPHP](https://redbeanphp.com/index.php?p=/many_to_many):
```
<?php
    list($vase, $lamp) = R::dispense('product', 2);

    $tag = R::dispense( 'tag' );
    $tag->name = 'Art Deco';

    //creates product_tag table!
    $vase->sharedTagList[] = $tag;
    $lamp->sharedTagList[] = $tag;
    R::storeAll( [$vase, $lamp] );
```

# Queries

* Prepared statment and parameter binding

[RedBeanPHP](https://redbeanphp.com/index.php?p=/querying)
```
<?php
    $books = R::findFromSQL('book',"
        SELECT *, count(*) OVER() AS total FROM book WHERE
         genre = ? ORDER BY title ASC OFFSET {$from} LIMIT {$to} 
    ", [ $bindings ],  [ 'total' ]);
    $book  = reset($books);
    $total = $book->info('total');
```

Don't use `mysql_real_escape`.

* Antipatterns

```
<?php
    $user = R::load( 'user', $id );
    if (empty($user)) {
      $book = R::dispense( 'user' );
      $id = R::store( $user )
    }
```

```
<?php
    try{
        R::store( $user );
    }
    catch( SQL $e ) {
        if ($e->getSQLState() == 23000) {
            $user = R::load( 'user', 'name = "Daniel" );
        }
    }
```


## Use transactions:

```
<?php
use Illuminate\Support\Facades\DB;
 
DB::transaction(function () {
    DB::update('update users set votes = 1');
 
    DB::delete('delete from posts');
});
```

[RedBeanPHP](https://redbeanphp.com/index.php?p=/database)
```
<?php
    R::transaction( function() {
        ..store some beans..
    } );
```


Lock modes:

[RedBeanPHP](https://redbeanphp.com/api/classes/R.html#findForUpdate)
```
<?php
    R::transaction( function() {
        $bean = R::findForUpdate( 'bean', ' title LIKE ? ', array('title') );
        $bean.title = 'new title';
        R::store( $bean );
    } );
```


# Indicies/Indexes 


Necessary when you search by more than one criteria:

```
<?php
    $bean = R::findForUpdate( 'bean', ' title LIKE ? and flavor = ?', array('mexican%', 'red') );
```

When compond indexes, order matters:
* fixed, range, join


[Doctrine](https://stackoverflow.com/questions/17991782/how-can-i-add-an-index-with-doctrine-2-to-a-column-without-making-it-a-primary-k)
```
<?php
/**
 * @Entity
 * @Table(name="ecommerce_products",indexes={
 *     @Index(name="search_idx", columns={"name", "email"})
 * })
 */
class ECommerceProduct
{
```


[RedBeanPHP](https://redbeanphp.com/index.php?p=/crud):

> RedBeanPHP will build all the necessary structures to store your data. However custom indexes and constraints have to be added manually (after freezing your web application). 

```
<?php
    require 'rb.php';
    R::setup();
    R::freeze( TRUE );
    R::exec( 'CREATE INDEX IF NOT EXIST search_index ON ecommerce_products (name, email)' );
```
