Sparql (module for Omeka S)
===========================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Sparql] is a module for [Omeka S] that allows to query json-ld Omeka S database
via the Sparql language. The query can be built via a text editor of via a
clickodrome.

The main interest of a sparql search agains api or sql search is that it is a
much more global search: queries are not limited to the database, but to all the
linked data. So this a powerful search tool useful when you have many relations
and normalized data (dates, subjects, etc.), in particular via the module [Value Suggest]
and values that uses common ontologies with right precise usage of each
properties and classes. If you have custom vocabularies, publish it and take it
stable to allow richer results.

So it is recommended to activate this module or to communicate on it only when
the database is well built.


Installation
------------

See general end user documentation for [installing a module].

The module [Common] must be installed first.

The module uses external libraries, so use the release zip to install it, or
use and init the source.

* From the zip

Download the last release [Sparql.zip] from the list of releases (the
master does not contain the dependency), and uncompress it in the `modules`
directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Sparql`, go to the root module, and run:

```sh
composer install --no-dev
```


Usage
-----

Like other search engine, the module requires to index data in the server. This
is done automatically each time a resource is saved, but it can be disabled and
processed manually.

To query the search engine, simply send query to https://example.org/sparql or
use the human interface at https://example.org/s/mysite/sparql.


TODO
----

- [ ] Human interface via https://sparnatural.eu/
- [ ] Readme for [Apache Jena Fuseki].
- [ ] Other sparql interface for fuseki when direct access (https://triply.cc/docs/yasgui/…), in particular yasgui gallery, charts and timeline plugins.
- [ ] Exploration tools of Nicolas Lasolle, that are adapted to Omeka S.
- [ ] Other visualization and exploration tools (see Nicolas Lasolle [abstract written for a congress]).
- [ ] Query on private resources.
- [ ] Create a TDB2 template adapted to Omeka.
- [ ] Make a cron task (module EasyAdmin).
- [ ] Integrate with module Advanced Search.
- [ ] Add button for indexing in module Advanced Search.
- [ ] Triple stores by site or via queries.
- [ ] Integrate full text search with lucene (see https://jena.apache.org/documentation/query/text-query.html)
- [ ] Use api credentials for sparql queries.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

* Module

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced prof[Apache Shiro]essionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

* Copyright Daniel Berthereau, 2023-2024 (see [Daniel-KM] on GitLab)

* Inspiration

An independant example of rdf visualization from an [Omeka S database] is https://henripoincare.fr
and the work made by [Nicolas Lasolle] for a [thesis in computing] on [Archives Henri Poincaré]
(see [abstract written for a congress]). You can see an example of querying and
results in a [short video].

The python tool [Omeka S to Rdf] is not used because EasyRdf is integrated in
Omeka, so conversion are automatically done.

* Funding

This module was developed for the future digital library [Manioc] of the Université
des Antilles et de la Guyane, currently managed via Greenstone.


[Sparql]: https://gitlab.com/Daniel-KM/Omeka-S-module-Sparql
[Omeka S]: https://omeka.org/s
[Value Suggest]: https://omeka.org/s/modules/ValueSuggest
[Apache Jena Fuseki]: https://jena.apache.org/documentation/fuseki2
[Installing a module]: https://omeka.org/s/docs/user-manual/modules/
[Sparql.zip]: https://github.com/Daniel-KM/Omeka-S-module-Sparql/releases
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Sparql/issues
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[Omeka S database]: http://henripoincare.fr/
[Nicolas Lasolle]: https://github.com/nlasolle
[Thesis in computing]: https://hal.univ-lorraine.fr/tel-03845484
[abstract written for a congress]: https://inserm.hal.science/LORIA-NLPKD/hal-03406713v1
[Archives Henri Poincaré]: https://www.ahp-numerique.fr/
[short video]: https://videos.ahp-numerique.fr/w/gjj2DJ9mZmVNKehwuDgWFk
[Omeka S to Rdf]: https://github.com/nlasolle/omekas2rdf
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Manioc]: https://manioc.org
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
