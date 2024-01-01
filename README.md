Sparql (module for Omeka S)
===========================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Sparql] is a module for [Omeka S] that create a triplestore and a sparql server
that allows to query json-ld Omeka S database via the [sparql language]. The
query can be built via a form in any page or via the endpoint, compliant with
the [sparql protocol version 1.0] and partially version 1.1.

The main interest of a sparql search against api or sql search is that it is a
much more global search: requests are not limited to the database, but to all
the linked data. So this a powerful search tool useful when you have many
relations and normalized data (dates, people, subjects, locations, etc.), in
particular via the module [Value Suggest] and values that uses common ontologies
with right precise usage of each properties and classes. If you have custom
vocabularies, publish them and take them stable to allow richer results.

Furthermore, results may be a list of data, but sparql graphs too.

**WARNING**: This is a work in progress and the [sparql protocol] is not fully
implemented yet.

For a big base or full support of the sparql specifications, in particular the
[sparql protocol version 1.1], it is recommended to use an external sparql server,
like [Fuseki] and to point it to the triplestore created by the module.


Installation
------------

### Module

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
the module to `Sparql`, go to the root of the module, and run:

```sh
composer install --no-dev
```

### Server allowing CORS (Cross-Origin Resource Sharing)

To make the endpoint available from any client, it should be [CORS] compliant.

On Apache 2.4, the module "headers" should be enabled:

```sh
a2enmod headers
systemctl restart apache2
```

Then, you have to add the following rules, adapted to your needs, to the file
`.htaccess` at the root of Omeka S or in the main config of the server:

```
# CORS access for some files.
<IfModule mod_headers.c>
    Header setIfEmpty Access-Control-Allow-Origin "*"
    Header setIfEmpty Access-Control-Allow-Headers "origin, x-requested-with, content-type"
    Header setIfEmpty Access-Control-Allow-Methods "GET, POST"
</IfModule>
```

It is recommended to use the main config of the server, for example  with the
directive `<Directory>`.

To fix Amazon cors issues, see the [aws documentation].


Usage
-----

### Indexation

Like other search engine, the module requires to index data in the server. This
is **not** done automatically each time a resource is saved, so the triplestore
should be but updated manually for now in the config form.

### Query

There are two ways to query the search engine.

To query the triplestore according to the standard [sparql protocol version 1.0],
go to https://example.org/sparql. This endpoint is designed for servers.

To query the triplestore with a human interface, create a page with the block
"sparql" and go to it.

If you use an external sparql server, just point it to the triplestore created
by the module.


TODO
----

- [ ] Support of sparql protocol version 1.1.
- [ ] Support of automatic pagination with the omeka paginator.
- [ ] Human interface via https://sparnatural.eu/
- [x] Yasgui interface.
- [ ] Other sparql interfaces than yasgui.
- [ ] Yasgui gallery, charts and timeline plugins (see https://yasgui.triply.cc).
- [ ] Incllude sparql graph by default.
- [ ] Exploration tools of Nicolas Lasolle, that are adapted to Omeka S.
- [ ] Other visualization and exploration tools (see Nicolas Lasolle [abstract written for a congress]).
- [ ] Triple stores by site or via queries.
- [ ] Manage multiple triplestores.
- [ ] Query on private resources.
- [ ] Use api credentials for sparql queries.
- [ ] Create a TDB2 template adapted to Omeka.
- [ ] Make a cron task (module [Easy Admin])?
- [ ] Integrate with module [Advanced Search] for indexation.
- [ ] Add button for indexing in module Advanced Search.
- [ ] Integrate full text search with lucene (see https://jena.apache.org/documentation/query/text-query.html)
- [ ] Readme for Apache Jena [Fuseki].
- [ ] Support create and update of resources through sparql and api.


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

* Libraries

- [semsol/arc2]: GPL-2.0-or-later or W3C.
- [TriplyDB/yasgui]: MIT


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
[Fuseki]: https://jena.apache.org/documentation/fuseki2
[sparql language]: https://www.w3.org/TR/2013/REC-sparql11-query-20130321
[sparql protocol version 1.0]: http://www.w3.org/TR/2008/REC-rdf-sparql-protocol-20080115
[sparql protocol version 1.1]: http://www.w3.org/TR/rdf-sparql-protocol
[Installing a module]: https://omeka.org/s/docs/user-manual/modules
[Sparql.zip]: https://github.com/Daniel-KM/Omeka-S-module-Sparql/releases
[CORS]: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
[aws documentation]: https://docs.aws.amazon.com/AmazonS3/latest/userguide/cors.html
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Sparql/issues
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[Easy Admin]: https://gitlab.com/Daniel-KM/Omeka-S-module-EasyAdmin
[Advanced Search]: https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedSearch
[Omeka S database]: http://henripoincare.fr
[Nicolas Lasolle]: https://github.com/nlasolle
[Thesis in computing]: https://hal.univ-lorraine.fr/tel-03845484
[abstract written for a congress]: https://inserm.hal.science/LORIA-NLPKD/hal-03406713v1
[Archives Henri Poincaré]: https://www.ahp-numerique.fr
[short video]: https://videos.ahp-numerique.fr/w/gjj2DJ9mZmVNKehwuDgWFk
[Omeka S to Rdf]: https://github.com/nlasolle/omekas2rdf
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[semsol/arc2]: https://github.com/semsol/arc2
[TriplyDB/yasgui]: https://github.com/TriplyDB/Yasgui
[Manioc]: https://manioc.org
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
