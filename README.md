# MongoDB Repository
![PHP7 Tested](http://php-eye.com/badge/nilportugues/mongodb-repository/php70.svg)
[![Build Status](https://travis-ci.org/PHPRepository/php-mongodb-repository.svg)](https://travis-ci.org/PHPRepository/php-mongodb-repository) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nilportugues/php-mongodb-repository/badges/quality-score.png??b=master)](https://scrutinizer-ci.com/g/nilportugues/php-mongodb-repository/?branch=master) [![SensioLabsInsight](https://insight.sensiolabs.com/projects/b6284e28-ca76-4836-b120-2341b168a7db/mini.png?gold)](https://insight.sensiolabs.com/projects/b6284e28-ca76-4836-b120-2341b168a7db) [![Latest Stable Version](https://poser.pugx.org/nilportugues/mongodb-repository/v/stable?)](https://packagist.org/packages/nilportugues/mongodb-repository) [![Total Downloads](https://poser.pugx.org/nilportugues/mongodb-repository/downloads?)](https://packagist.org/packages/nilportugues/mongodb-repository) [![License](https://poser.pugx.org/nilportugues/mongodb-repository/license?)](https://packagist.org/packages/nilportugues/mongodb-repository)
[![Donate](https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif)](https://paypal.me/nilportugues)

MongoDB Repository using *[nilportugues/repository](https://github.com/nilportugues/php-repository)* as foundation using *[mongodb/mongodb](https://github.com/mongodb/mongo-php-library)*.

## Installation

Use [Composer](https://getcomposer.org) to install the package:

```json
$ composer require nilportugues/mongodb-repository
```

## Why? Drivers and Multiple Implementations!

Using this implementation you can switch it out to test your code without setting up databases.

Doesn't sound handy? Let's think of yet another use case you'll love using this. `Functional tests` and `Unitary tests`.

No database connection will be needed, nor fakes. Using an `InMemoryRepository` or `FileSystemRepository` implementation will make those a breeze to code. And once the tests finish, all data may be destroyed with no worries at all.

**Available drivers:**

Also, if you feel like changing the repository implementation, no logic changes would be needed, as there are a set of drivers for you to use out of the box:

- `composer require nilportugues/repository-cache` for [caching](https://github.com/nilportugues/php-repository-cache).
- `composer require nilportugues/repository` for an [InMemoryRepository implementation](https://github.com/nilportugues/php-repository).
- `composer require nilportugues/filesystem-repository` for a [FileSystemRepository implementation](https://github.com/nilportugues/php-filesystem-repository).
- `composer require nilportugues/eloquent-repository` for a [SQL Eloquent implementation](https://github.com/nilportugues/php-eloquent-repository).
- `composer require nilportugues/doctrine-repository` for a [SQL Doctrine implementation](https://github.com/nilportugues/php-doctrine-repository).
- `composer require nilportugues/eloquent-mongodb-repository` for a [MongoDB Eloquent implementation](https://github.com/nilportugues/php-eloquent-mongodb-repository).




## Usage

This is how you use MongoDB in any project. 

```php
<?php
$uri = 'mongodb://localhost:27017';
$client = new \MongoDB\Client($uri));
```

Now that MongoDB is running, we can use the Repository.

### One Repository for One MongoDB Model

To be faithful to the repository pattern, using MongoDB Models internally is OK, Business objects should be returned. 

Therefore, you should translate MongoDB to Business representations and the other way round. This is represented by `$userAdapter` in the example below.

The fully implementation should be along the lines:

```php
<?php
use NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB\MongoDBRepository;

class UserRepository extends MongoDBRepository 
{
    protected $userAdapter;       
    protected $primaryKey = 'userId'; //if not set, defaults to "id"
    
    /**
     * @param \MongoDB\Client $client
     * @param $userAdapter
     */
    public function __construct($client, $userAdapter)
    {
        $this->userAdapter = $userAdapter; 
        parent::__construct($client, 'databaseName', 'collectionName');
    }
    
    /**
     * {@inheritdoc}
     */    
    public function find(Identity $id, Fields $fields = null)
    {
        $model = parent::find($id, $fields);   
        
        return $this->userAdapter->fromMongoDB($model);
    }
    
    /**
     * {@inheritdoc}
     */    
    public function findBy(Filter $filter = null, Sort $sort = null, Fields $fields = null)
    {
        $modelArray = parent::findBy($filter, $sort, $fields);   
        
        return $this->fromMongoDBArray($modelArray);
    }       
    
    /**
     * {@inheritdoc}
     */
    public function findAll(Pageable $pageable = null)
    {
        $page = parent::findAll($pageable);
        
        return new Page(
            $this->fromMongoDBArray($page->content()),
            $page->totalElements(),
            $page->pageNumber(),
            $page->totalPages(),
            $page->sortings(),
            $page->filters(),
            $page->fields()
        );
    } 

   /**
    * @param array $modelArray
    * @return array
    */
   protected function fromMongoDBArray(array $modelArray)
   {
        $results = [];
        foreach ($modelArray as $model) {            
            $results[] = $this->userAdapter->fromMongoDB($model);
        }
        
        return $results;
   } 
}
```

A sample implementation can be found in the [/example](https://github.com/nilportugues/php-mongodb-repository/tree/master/example) directory.

### One MongoDBRepository for All MongoDB Models

While **this is not the recommended way**, as a repository should only return one kind of Business objects, this works for RAD projects.

While the amount of core is less than the previous example, bare in mind that your code will be coupled with MongoDB's data structure.


```php
<?php
use NilPortugues\Foundation\Infrastructure\Model\Repository\MongoDB\MongoDBRepository;

class UserRepository extends MongoDBRepository 
{
    
}

class BlogPostRepository extends MongoDBRepository 
{
    
}
```

## Filtering data

Filtering is as simple as using the `Filter` object. For instance, lets retrieve how many users are named `Ken`. 
 
```php
<?php
use NilPortugues\Foundation\Domain\Model\Repository\Filter;

$repository = new UserRepository();

$filter = new Filter();
$filter->must()->contain('name', 'Ken');

echo $repository->count($filter);
```

Notice how the key `name` matches the database column `name` in the `users` table.

**Available options**

Filter allow you to use `must()`, `mustNot()` and `should()` methods to set up a fine-grained search. These provide a fluent interface with the following methods available: 
    
- `public function notEmpty($filterName)`
- `public function hasEmpty($filterName)`
- `public function startsWith($filterName, $value)`
- `public function endsWith($filterName, $value)`
- `public function equal($filterName, $value)`
- `public function notEqual($filterName, $value)`
- `public function includeGroup($filterName, array $value)`
- `public function notIncludeGroup($filterName, array $value)`
- `public function range($filterName, $firstValue, $secondValue)`
- `public function notRange($filterName, $firstValue, $secondValue)`
- `public function notContain($filterName, $value)`
- `public function contain($filterName, $value)`
- `public function beGreaterThanOrEqual($filterName, $value)`
- `public function beGreaterThan($filterName, $value)`
- `public function beLessThanOrEqual($filterName, $value)`
- `public function beLessThan($filterName, $value)`
    
## Sorting data

Sorting is straight forward. Create an instance of Sort and pass in the column names and ordering.

```php
<?php
use NilPortugues\Foundation\Domain\Model\Repository\Sort;

$repository = new UserRepository();

$filter = null; //all records
$sort = new Sort(['name', 'id'], new Order('ASC', 'DESC'));
$fields = null; //all columns

$results = $repository->findBy($filter, $sort, $fields);
```

## Fields data

Create a Fields object to fetch only selected columns. If no Fields object is passed, all columns are selected by default.

```php
<?php
use NilPortugues\Foundation\Domain\Model\Repository\Contracts\Fields;

$repository = new UserRepository();

$filter = null; //all records
$sort = null; //existing order
$fields = new Fields(['name', 'id']);

$results = $repository->findBy($filter, $sort, $fields);
```

## Fetching data

Repository allows you to fetch data from the database by using the following methods:

- `public function findAll(Pageable $pageable = null)`
- `public function find(Identity $id, Fields $fields = null)`
- `public function findBy(Filter $filter = null, Sort $sort = null, Fields $fields = null)`


## Quality

To run the PHPUnit tests at the command line, go to the tests directory and issue phpunit.

This library attempts to comply with [PSR-1](http://www.php-fig.org/psr/psr-1/), [PSR-2](http://www.php-fig.org/psr/psr-2/), [PSR-4](http://www.php-fig.org/psr/psr-4/).

If you notice compliance oversights, please send a patch via [Pull Request](https://github.com/nilportugues/php-mongodb-repository/pulls).


## Contribute

Contributions to the package are always welcome!

* Report any bugs or issues you find on the [issue tracker](https://github.com/nilportugues/php-mongodb-repository/issues/new).
* You can grab the source code at the package's [Git Repository](https://github.com/nilportugues/php-mongodb-repository).


## Support

Get in touch with me using one of the following means:

 - Emailing me at <contact@nilportugues.com>
 - Opening an [Issue](https://github.com/nilportugues/php-mongodb-repository/issues/new)


## Authors

* [Nil Portugués Calderó](http://nilportugues.com)
* [The Community Contributors](https://github.com/nilportugues/php-mongodb-repository/graphs/contributors)


## License
The code base is licensed under the [MIT license](LICENSE).
