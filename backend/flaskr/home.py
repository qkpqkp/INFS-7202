# -*- coding: utf-8 -*-
"""
Created on Wed Mar 18 13:30:05 2020

@author: Doly
"""

from flask import (
    Blueprint, flash, g, redirect, render_template, request, url_for
)
from werkzeug.exceptions import abort

from flaskr.auth import login_required
from flaskr.db import get_db

bp = Blueprint('home', __name__)

@bp.route('/')
def index():

    return render_template('home/index.html')