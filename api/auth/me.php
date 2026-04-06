<?php
/** Auth - Get current user */
Auth::requireAuth();
Response::success(Auth::user());
