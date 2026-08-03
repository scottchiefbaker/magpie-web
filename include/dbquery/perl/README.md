# Perl DBQuery

DBQuery is a Perl database library to simplfy fetching data from your database. It supports
any database that the DBI supports, and has been tested extensively on MySQL and SQLite.

## Installation

	require("/path/to/DB/Query.pm");

## Example Usage

```
my $dsn = "DBI:SQLite:dbname=/tmp/database.sqlite";
my $dbq = new DB::Query($dsn);

my $sql = "SELECT * FROM Table LIMIT 10;";
my $x   = $dbq->query($sql);

foreach my $rec ($x) {
	printf("%s is customer #%d", $rec->{'CustName'}, $rec->{'CustID'});
}
```
