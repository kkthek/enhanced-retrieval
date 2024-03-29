/*
 * Copyright (C) Vulcan Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.If not, see <http://www.gnu.org/licenses/>.
 *
 */

// Due to the ResourceLoader Prototype must be run in a closure. The function $
// polutes the global variable space. It is replaced by $P.
// All scripts that include this file are executed inside a closure. It is safe
// to let $ point to Prototype's $
console.log("ER: Loading scripts/initPrototype.js");

var $ = $P;  
