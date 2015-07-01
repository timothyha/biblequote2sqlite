# -*- encoding: utf-8 -*-

# Contributors: Eduard Stepanov (e-stepanov), Timothy Ha (timothyha)

import os
import sqlite3
from ConfigParser import SafeConfigParser, NoOptionError
import config

create_binary_table_query = "CREATE TABLE binary ( serial integer PRIMARY KEY AUTOINCREMENT NOT NULL, filename varchar(256) DEFAULT(NULL), fileref varchar(256), num integer DEFAULT(0), filedata blob DEFAULT(NULL) );"
create_books_table_query = "CREATE TABLE books ( serial integer PRIMARY KEY AUTOINCREMENT NOT NULL, num integer, bookid char(16), chapters integer, fullname char(64), shortnames char(256) );"
create_chapters_table_query = "CREATE TABLE chapters ( serial integer PRIMARY KEY AUTOINCREMENT NOT NULL, book integer, chapter integer, title char(256) );"
create_chaptersbook_index_query = "CREATE INDEX chaptersbook ON chapters (book);"
create_contents_table_query = "CREATE TABLE contents ( serial integer PRIMARY KEY AUTOINCREMENT NOT NULL, book integer, chapter integer, verse integer, txt text(4096) DEFAULT(NULL), num integer DEFAULT(0) );"
create_contentsbook_index_query = "CREATE INDEX contentsbook ON contents (book);"
create_contentschapter_index_query = "CREATE INDEX contentschapter ON contents (chapter);"
create_contentverse_index_query = "CREATE INDEX contentsverse ON contents (verse);"
create_info_table_query = "CREATE TABLE info ( serial integer PRIMARY KEY AUTOINCREMENT, \"key\" varchar(64) DEFAULT(NULL), value varchar(1024) DEFAULT(NULL), num integer DEFAULT(0) );"
create_db_queries = [create_binary_table_query, create_books_table_query, create_chapters_table_query, create_chaptersbook_index_query, create_contents_table_query, \
                    create_contentsbook_index_query, create_contentschapter_index_query, create_contentverse_index_query, create_info_table_query]

book_properties = ['PathName', 'FullName', 'ShortName', 'ChapterQty']

def parse_bibleqt_ini(path):
    """
    Parses bibleqt.ini file and returns module's parameters (module_info variable) and information about books (books variable) in modules.
    modules_info and books_info are dicts.
    The structure of module_info dict is {'param_name1': 'parame_value1', ...}
    The structure of books dict is {'book_number1': {'PathName': '...', 'FullName': '...', 'ShortName': '...', 'ChapterQty': '...''}} 
    """
    with open(path, 'r') as f:
        books_info = {}
        modules_info = {}
        current_book_number = 0
        for line in f:
            if is_property_string(line):
                param, value = line.split('=')
                param = param.strip()
                value = value.strip()
                if param == "PathName":
                    current_book_number += 1
                    books_info[current_book_number] = {}
                if is_module_property(param):
                    modules_info[param] = value
                else:
                    books_info[current_book_number][param] = value
        return modules_info, books_info


            
def is_property_string(s):
    #check if s is property or comment or just empty line. Property string always has symbol '='
    if s.startswith('//'):
        return False
    if s.startswith(';'):
        return False
    return '=' in s

def is_module_property(param):
    #is it book or module property
    return not param in book_properties

def execute_queries(db_name, queries):
    conn = sqlite3.connect(db_name)
    c = conn.cursor()
    for query in queries:
        c.execute(query)
    conn.commit()
    conn.close()


def create_db(name):
    conn = sqlite3.connect(name)
    execute_queries(name, create_db_queries)

def fill_info_table(db_name, modules_info, books_info):
    """
        Information about structure of modules_info and books_info is in parse_bibleqt_ini method
    """
    queries = ["INSERT INTO info(key, value) VALUES ('ModuleFormatVersion', '1.0');"]
    for item in modules_info.keys():
        queries.append('INSERT INTO info(key,value,num) VALUES ("%(key)s", "%(value)s", "0")' % {'key': item, 'value': modules_info[item]})
    for book_number in books_info.keys():
        for param_name in book_properties:
            queries.append('INSERT INTO info(key,value,num) VALUES ("%(key)s", "%(value)s", "%(num)s")' % \
                                            {'key': param_name,
                                            'value': books_info[book_number][param_name],
                                            'num': book_number})
    execute_queries(db_name, queries)

def get_possible_fullnames():
    parser = SafeConfigParser()
    parser.read(config.FULLNAMES_FILE)
    return parser

def get_osis_by_fullname(fullname):
    fullnames = get_possible_fullnames()
    for osis in config.osis77:
        try:
            if fullname.lower() in fullnames.get("fullnames", osis).lower():
                return osis
        except NoOptionError:
            pass
    return None

def fill_books_table(db_name, modules_info, books_info):
    queries = []
    for b in books_info.keys():
        query = "INSERT INTO books(num, bookid, chapters, fullname, shortnames) VALUES ('%(num)s', '%(bookid)s', '%(chapters)s', '%(fullname)s', '%(shortnames)s');" % \
                {'num': b,
                 'bookid': get_osis_by_fullname(books_info[b]['FullName']),
                 'chapters': books_info[b]['ChapterQty'],
                 'fullname': books_info[b]['FullName'],
                 'shortnames': books_info[b]['ShortName']}
        queries += [query]
    execute_queries(db_name, queries)



if __name__ == "__main__":
    modules_info, books_info = parse_bibleqt_ini("../modules/NT_Russian_jews/Bibleqt.ini")
    db_name = "NT_Russian_jews.sqlite"
    create_db(db_name)
    fill_info_table(db_name, modules_info, books_info)
    fill_books_table(db_name, modules_info, books_info)

