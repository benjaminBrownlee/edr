
drop database if exists EDR;
create database EDR;
use EDR;

create table users (
    id int(100) unsigned not null auto_increment,
    uuid varchar(100) not null,
    primary key (id)
);

create table vehicles (
    id int(100) unsigned not null auto_increment,
    year int(100) unsigned,
    model varchar(100),
    make varchar(100),
    vin varchar(100) not null,
    primary key (id)
);

create table drives (
    id int(100) unsigned not null auto_increment,
    user int(100) unsigned not null,
    vehicle int(100) unsigned not null,
    starttime datetime,
    stoptime datetime,
    distance int(100),
    runtime int(100),
    events int(100),
    primary key (id),
    foreign key (user) references users (id),
    foreign key (vehicle) references vehicles (id)
);

create table segments (
    id int(100) unsigned not null auto_increment,
    time datetime not null,
    drive int(100) unsigned not null,
    runtime int(10) unsigned not null,
    distance int(10) unsigned not null,
    speed int(10) unsigned not null,
    events varchar(256),
    primary key (id),
    foreign key (drive) references drives (id)
);