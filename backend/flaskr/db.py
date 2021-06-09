import click
import MySQLdb
import MySQLdb.cursors
import os
import sqlite3
from flask import current_app, g
from flask.cli import with_appcontext

def get_db():
    """Connect to the application's configured database. The connection
    is unique for each request and will be reused if this is called
    again.
    """
    if 'db' not in g:
        environment = os.environ.get('FLASK_ENV')

        if environment == 'development':
            g.db = sqlite3.connect(
            current_app.config['DATABASE'],
            detect_types=sqlite3.PARSE_DECLTYPES
            )
            g.db.row_factory = sqlite3.Row
        elif environment == 'test':
            g.db = MySQLdb.connect(host="18.191.168.236",  # your host
                         user="qkpqkp",       # username
                         passwd="abcdqqqq",     # password
                         db="database-1",# name of the database
                         cursorclass=MySQLdb.cursors.DictCursor)
        else:
            # `export FLASK_ENV=production`
            g.db = MySQLdb.connect(host="35.192.163.20",  # your host
                    user="root",       # username
                    passwd="root",     # password
                    db="mysqldb",# name of the database
                    cursorclass=MySQLdb.cursors.DictCursor)

    return g.db


def close_db(e=None):
    """If this request connected to the database, close the
    connection.
    """
    db = g.pop('db', None)

    if db is not None:
        db.close()


def init_db():
    """Clear existing data and create new tables."""
    db = get_db()


    environment = os.environ.get('FLASK_ENV')

    if environment == 'test':
        with current_app.open_resource('test_schema.sql') as f:
            schema = f.read().decode('utf8')
    else:
        # `export FLASK_ENV=production` or `export FLASK_ENV=development`
        with current_app.open_resource('schema.sql') as f:
            schema = f.read().decode('utf8')
           
    sql_commands = schema.split(';')

    for i in range(len(sql_commands)):
        if (i==len(sql_commands)-1):
            break
        command = sql_commands[i].replace('\n','')
        command = command.replace('\t','') + ';'
        db.cursor().execute(command)
    return

@click.command('init-db')
@with_appcontext
def init_db_command():
    """Clear existing data and create new tables."""
    init_db()
    click.echo('Initialized the database.')


def init_app(app):
    """Register database functions with the Flask app. This is called by
    the application factory.
    """
    app.teardown_appcontext(close_db)
    app.cli.add_command(init_db_command)
