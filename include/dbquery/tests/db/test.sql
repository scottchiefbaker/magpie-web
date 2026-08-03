CREATE TABLE "Customer" (
    "First" TEXT NOT NULL,
    "Last" TEXT NOT NULL,
    "City" TEXT,
    "State" TEXT,
    "Zipcode" INTEGER,
    "CustID" INTEGER PRIMARY KEY NOT NULL
);
INSERT INTO "Customer" VALUES('Jason','Doolis','Canby','OR',97013,1);
INSERT INTO "Customer" VALUES('Jasmine','Arkenhammer','Salem','OR',91341,2);
INSERT INTO "Customer" VALUES('Justin','Donk','Whichita','KA',15134,3);
INSERT INTO "Customer" VALUES('Chris','Anderson','Portland','OR',71923,4);
INSERT INTO "Customer" VALUES('James','Vanderbilt','Austin','TX',75013,5);
INSERT INTO "Customer" VALUES('Gene','Ramsey','Orlando','FL',34854,6);
INSERT INTO "Customer" VALUES('Sean','Hammerhead','Miwaukee','WI',68514,7);
INSERT INTO "Customer" VALUES('Marcus','Ormstump','Columbus','OH',29841,8);
INSERT INTO "Customer" VALUES('Susan','Lewiston','Las Vegas','IL',39139,9);
INSERT INTO "Customer" VALUES('Kristen','Stewart','Detroit','MI',90494,10);

CREATE TABLE "items" (
    "ItemID" INTEGER PRIMARY KEY NOT NULL,
    "ItemDesc" TEXT,
    "ItemCost" FLOAT
);
INSERT INTO "items" VALUES(1,'Chocolate Chips',2.76);
INSERT INTO "items" VALUES(2,'Canned Air',1.5);
INSERT INTO "items" VALUES(3,'Cheetos',2.15);
INSERT INTO "items" VALUES(4,'Grass Seed',15.79);
INSERT INTO "items" VALUES(5,'Coffee Mug',3.19);
INSERT INTO "items" VALUES(6,'Flash Drive',8.19);
INSERT INTO "items" VALUES(7,'Arduino',35);
INSERT INTO "items" VALUES(8,'CD Holder',8.1);
INSERT INTO "items" VALUES(9,'Microsoft Windows 8',115.64);
INSERT INTO "items" VALUES(10,'USB Cable',4.27);
INSERT INTO "items" VALUES(11,'Magnet',0.13);
INSERT INTO "items" VALUES(12,'Headphones',67.24);

CREATE TABLE "orders" (
    "OrderID" INTEGER PRIMARY KEY,
    "ItemID" INTEGER,
    "CustID" INTEGER,
    "ItemCount" INTEGER
);
