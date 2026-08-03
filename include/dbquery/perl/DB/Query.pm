#!/usr/bin/env perl

use strict;
use warnings;
use DBI;

use constant DB_QUERY_VERSION => '1.1.1';

##############################################################################
# Example usage:
#
# use DB::Query;
# my $dsn = "DBI:SQLite:dbname=/tmp/database.sqlite";
# my $dbq = new DB::Query($dsn);
#
# my $sql = "SELECT * FROM Table LIMIT 10;";
# my $x   = $dbq->query($sql);
###############################################################################

package DB::Query;

sub new {
	my ($class, $dsn, $user, $pass, $opts) = @_;

	# Turn on RaiseError if it's not set to off explicitly
	if (!defined($opts->{RaiseError})) {
		$opts->{RaiseError} = 1;
	}

	my $dbh = DBI->connect($dsn, $user, $pass, $opts);

	my $self = {
		dsn   => $dsn,
		user  => $user,
		dbh   => $dbh,
		debug => 0,
	};

	my $ret = bless($self, $class);

	return $ret;
}

sub query {
	my ($self,$one,$two,$three) = @_;
	my $sql = $self->trim($one);

	# If the second element is an array ref it's params
	my @bind_params = ();
	if (ref($two) eq "ARRAY") {
		@bind_params = @$two;
		$two         = undef; # To not confuse the next check
	}

	# ReturnType is the last passed in element (or defaults to info_hash)
	my $type = $three || $two || "info_hash";

	# If the type is ARRAY it's params with a return type so we default
	if (ref($type) eq "ARRAY") {
		$type = "info_hash";
	}

	my $dbh = $self->{'dbh'};
	my $ret = [];
	my ($sth,$rows);

	if ($self->{'debug'}) {
		print $self->color("yellow");
		print "SQL    : $sql\n";
		print "Type   : $type\n";
		print "Params : " . join(", ", @bind_params) . "\n";
		print $self->color("reset");
		print "\n";
	}

	# If there are params we need prepare
	if (@bind_params || $sql =~ /^(SELECT|SHOW)/i) {
		eval {
			$sth = $dbh->prepare($sql);
			$sth->execute(@bind_params) or die $sth->errstr;
		};

		if ($@) {
			die($self->color('orange') . "Error on prepare: " . color('reset') . "$@\n");
		}

		$rows = $sth->rows;
	} else {
		$rows = $dbh->do($sql);
	}

	if ($sql =~ /INSERT|REPLACE/) {
		$ret = $dbh->last_insert_id();

		if (!defined($ret)) {
			$ret = -1;
		}
	} elsif (!$sth) {
		$ret = $rows;
	} elsif ($sql =~ /DELETE|UPDATE|TRUNCATE/) {
		$ret = $rows;
	} elsif ($type eq 'one_data') {
		my $data = $sth->fetchrow_arrayref();
		$ret = $data->[0];
	} elsif ($type eq 'one_row') {
		$ret = $sth->fetchrow_hashref();
	} elsif ($type eq 'info_list') {
		while (my @data = $sth->fetchrow_array()) {
			push(@$ret,\@data);
		}
	} elsif ($type eq 'info_hash') {
		while (my $foo = $sth->fetchrow_hashref()) {
			push(@$ret,$foo);
		}
	} elsif ($type eq 'one_column') {
		while (my $foo = $sth->fetchrow_arrayref()) {
			push(@$ret,$foo->[0]);
		}
	} elsif ($type eq 'key_value') {
		while (my $data = $sth->fetchrow_arrayref()) {
			my $key   = $data->[0];
			my $value = $data->[1];
			$ret->{$key} = $value;
		}
	} else {
		die("Unknown type $type\n");
	}

	return $ret;
}

# String format: '115', '165_bold', '10_on_140', 'reset', 'on_173', 'red', 'white_on_blue'
sub color {
	my $self = shift();
	my $str  = shift();

	# If we're NOT connected to a an interactive terminal don't do color
	if (-t STDOUT == 0) { return ''; }

	# No string sent in, so we just reset
	if (!length($str) || $str eq 'reset') { return "\e[0m"; }

	# Some predefined colors
	my %color_map = qw(red 160 blue 27 green 34 yellow 226 orange 214 purple 93 white 15 black 0);
	$str =~ s|([A-Za-z]+)|$color_map{$1} // $1|eg;

	# Get foreground/background and any commands
	my ($fc,$cmd) = $str =~ /^(\d{1,3})?_?(\w+)?$/g;
	my ($bc)      = $str =~ /on_(\d{1,3})$/g;

	# Some predefined commands
	my %cmd_map = qw(bold 1 italic 3 underline 4 blink 5 inverse 7);
	my $cmd_num = $cmd_map{$cmd // 0};

	my $ret = '';
	if ($cmd_num)     { $ret .= "\e[${cmd_num}m"; }
	if (defined($fc)) { $ret .= "\e[38;5;${fc}m"; }
	if (defined($bc)) { $ret .= "\e[48;5;${bc}m"; }

	return $ret;
}

sub trim {
	my $self = shift();

	if (wantarray) {
		my @ret;
		foreach (@_) {
			push(@ret,scalar(trim($_)));
		}

		return @ret;
	} else {
		my $s = shift();
		if (!defined($s) || length($s) == 0) { return ""; }
		$s =~ s/^\s*//;
		$s =~ s/\s*$//;

		return $s;
	}
}

1; # Perl packages have to return 1

# vim: tabstop=4 shiftwidth=4 autoindent softtabstop=4
