### Wed, Feb 3, 2016
get codeception to run
continue w parser.php
learn a bit more about composer

checklist:
write unit test for parser.php

-/-
### Thurs, Feb 4, 2016
wrote a bit of code. simple data now gets imported just fine.
ran into a problem w codeception where it executes sql query twice

#checklist:
post question on stackoverflow
learn markdown syntax

#brainstorm:
report insertGroups to insert multiple menu groups

#questions:
menugroup id: no auto_increment? manually imported?

-/-
### Fri, Feb 5, 2016

submitted question to stackoverflow.
working on importing multiple menu groups.

#brainstorm:
*importing multi menu groups*
method 1:
store info of super-level within the sub-level array -- store menu group name in array 'categories':
    create associative arrays when processing csv data: 
    [sub-level item] => [name of super-level item]
makes life a bit easier: enforce CSV format

import all menu groups

import categories:
with each category:
    look up its super-level item (using menugroup name) in database and retrieve its id 
    import category using super-level id

=> key: how to find id of imported menu group? 
=> solution: use last_insert_id if menugroupid is primary key. if not?



_tasks_
* how to mark which item belongs to which super-level?
* do same menu groups in cs_menugroup have same menugroupid and different locationid (obviously), or different values for both?
    if same menugroupid: 
    search for existing id and use it
    if not: 
    unique menugroupid 

### Fri, Feb 19, 2016
# validating data
unwanted commas
empty names
format errors:
    size 1.2 => still gets imported as 1

### Wed, Feb 24
added two upload forms

add a validation gate for empty csv data/empty file
separate two parsers for mods and menu

### Fri, Mar 4
tasks: 
* how to check for duplicate groups, categories and items to allow update?

### Wed, Mar 9
test cases:
duplicate groups for different location id  

done:
adding duplicate categories between different groups
adding duplicate items between different categories
adding new groups

test process:
1. add original menu 
=> successful
2. add new categories 
expected behavior: add on top of original 
=> successful
3. update existing categories: add new size + items => successful
expected behavior: add only the new sizes and new items
4. updating duplicate items (same category and same name)
expected behavior: do nothing (even though prices are changed) 
=> successful

possible updates:
add new groups to same location - tested
add new categories to same group and to different group - tested
add new sizes to existing category - tested 
add new items to same category and to different category - tested
 
-/-
CSV FORMAT
**assumptions**
* no location will have duplicate menu groups
* no menu groups will have duplicate menu categories. however, a location can have duplicate menu categories.
* no menu categories will have duplicate menu items. however, a location and a menu group can have duplicate menu items, as long as they have different menu categories.
* no location will have duplicate modifier groups
* csv files from clients have to adopt the exact column order
* custom labels have to be declared in order













