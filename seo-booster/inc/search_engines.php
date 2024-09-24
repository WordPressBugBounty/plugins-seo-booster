<?php
// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sengine = array(
	array(
		'u' => 'maps.google.com',
		'q' => 'q',
	),
	array(
		'u' => '360.cn',
		'q' => 'q',
	),
	array(
		'u' => 'aliceadsl.fr',
		'q' => 'qs',
	),
	array(
		'u' => 'www.alice.com',
		'q' => 'qs',
	),
	array(
		'u' => 'alltheweb.com',
		'q' => 'q',
	),
	array(
		'u' => 'altavista.com',
		'q' => 'q',
	),
	array(
		'u' => 'aol.com',
		'q' => 'encquery',
	),
	array(
		'u' => 'aol.com',
		'q' => 'query',
	),
	array(
		'u' => 'aol.com',
		'q' => 'q',
	),
	array(
		'u' => '.wp.pl',
		'q' => 'szukaj',
	),
	array(
		'u' => 'voila.fr',
		'q' => 'rdata',
	),
	array(
		'u' => 'search.virgilio.it',
		'q' => 'qs',
	),
	array(
		'u' => 'search.tut.by',
		'q' => 'query',
	),
	array(
		'u' => 'buscador.terra.com.br',
		'q' => 'query',
	),
	array(
		'u' => 'isearch.avg.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.auone.jp',
		'q' => 'q',
	),
	array(
		'u' => 'search.babylon.com',
		'q' => 'q',
	),
	array(
		'u' => 'baidu.com',
		'q' => 'wd',
	),
	array(
		'u' => 'baidu.com',
		'q' => 'word',
	),
	array(
		'u' => 'alicesuche.aol.de',
		'q' => 'q',
	),
	array(
		'u' => 'search.aol.fr',
		'q' => 'q',
	),
	array(
		'u' => 'biglobe.ne.jp',
		'q' => 'q',
	),
	array(
		'u' => 'search.centrum.cz',
		'q' => 'q',
	),
	array(
		'u' => 'search.comcast.net',
		'q' => 'q',
	),
	array(
		'u' => 'search.conduit.net',
		'q' => 'q',
	),
	array(
		'u' => '.cnn.com/SEARCH/',
		'q' => 'qurtu',
	),
	array(
		'u' => 'daum.net',
		'q' => 'q',
	),
	array(
		'u' => 'ekolay.net',
		'q' => 'q',
	),
	array(
		'u' => '.eniro.se',
		'q' => 'search_word',
	),
	array(
		'u' => '.globo.com/busca/',
		'q' => 'q',
	),
	array(
		'u' => 'go.mail.ru',
		'q' => 'q',
	),
	array(
		'u' => 'goo.ne.jp',
		'q' => 'mt',
	),
	array(
		'u' => '.haosou.com/s',
		'q' => 'q',
	),
	array(
		'u' => 'search.incredimail.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.lycos.',
		'q' => 'query',
	),
	array(
		'u' => 'kvasir.no',
		'q' => 'q',
	),
	array(
		'u' => 'www.mynet.com',
		'q' => 'q',
	),
	array(
		'u' => '.naver.com',
		'q' => 'query',
	),
	array(
		'u' => 'najdi.si',
		'q' => 'q',
	),
	array(
		'u' => 'www.msn.com',
		'q' => 'q',
	),
	array(
		'u' => 'money.msn.com',
		'q' => 'q',
	),
	array(
		'u' => 'local.msn.com',
		'q' => 'q',
	),
	array(
		'u' => 'lycos.com',
		'q' => 'q',
	),
	array(
		'u' => '.mamma.com',
		'q' => 'query',
	),
	array(
		'u' => 'rakuten.co.jp',
		'q' => 'qt',
	),
	array(
		'u' => 'rambler.ru',
		'q' => 'query',
	),
	array(
		'u' => 'ozu.es',
		'q' => 'q',
	),
	array(
		'u' => 'szukaj.onet.pl',
		'q' => 'q',
	),
	array(
		'u' => 'szukaj.onet.pl',
		'q' => 'qt',
	),
	array(
		'u' => 'search.netscape.com',
		'q' => 'query',
	),
	array(
		'u' => 'search.smt.docomo.ne.jp',
		'q' => 'mt',
	),
	array(
		'u' => 'sesam.no',
		'q' => 'q',
	),
	array(
		'u' => 'search.ukr.net',
		'q' => 'q',
	),
	array(
		'u' => 'szukacz.pl',
		'q' => 'q',
	),
	array(
		'u' => 'seznam.cz',
		'q' => 'q',
	),
	array(
		'u' => 'search-results.com',
		'q' => 'q',
	),
	array(
		'u' => 'sogou.com',
		'q' => 'query',
	),
	array(
		'u' => '.so.com/s',
		'q' => 'q',
	),
	array(
		'u' => 'startsiden.no/sok',
		'q' => 'q',
	),
	array(
		'u' => 'yam.com',
		'q' => 'k',
	),
	array(
		'u' => 'bing.',
		'q' => 'q',
	),
	array(
		'u' => 'search.live.com',
		'q' => 'q',
	),
	array(
		'u' => 'seznam.cz',
		'q' => 'q',
	),
	array(
		'u' => 'filestube.com',
		'q' => 'q',
	),
	array(
		'u' => 'searchfunmoods.com',
		'q' => 'q',
	),
	array(
		'u' => 'searchmobileonline.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.certified-toolbar.com',
		'q' => 'q',
	),
	array(
		'u' => 'isearch.alot.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.alot.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.zonealarm.com',
		'q' => 'q',
	),
	array(
		'u' => '60searchengines.com',
		'q' => 'q',
	),
	array(
		'u' => 'video.google.',
		'q' => 'q',
	),
	array(
		'u' => 'ask.',
		'q' => 'q',
	),
	array(
		'u' => 'looksmart.',
		'q' => 'qt',
	),
	array(
		'u' => 'web.de',
		'q' => 'su',
	),
	array(
		'u' => 'fireball.de',
		'q' => 'query',
	),
	array(
		'u' => 'lycos.de',
		'q' => 'query',
	),
	array(
		'u' => 'lycos.com',
		'q' => 'query',
	),
	array(
		'u' => 'dogpile.com',
		'q' => 'q',
	),
	array(
		'u' => 'aol.com',
		'q' => 'query',
	),
	array(
		'u' => 'aol.com',
		'q' => 'q',
	),
	array(
		'u' => 'talktalk.co.uk',
		'q' => 'query',
	),
	array(
		'u' => 'best-price.com',
		'q' => 'query',
	),
	array(
		'u' => 'ask.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.conduit.com',
		'q' => 'q',
	),
	array(
		'u' => 'jauhari.net',
		'q' => 'search',
	),
	array(
		'u' => 'search.iminent.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.incredibar.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.sweetim.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.b1.org',
		'q' => 'q',
	),
	array(
		'u' => 'taringa.net',
		'q' => 'q',
	),
	array(
		'u' => 'mysearchresults.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.snap.do',
		'q' => 'q',
	),
	array(
		'u' => 'ricerca.virgilio.it',
		'q' => 'qs',
	),
	array(
		'u' => 'searchya.com',
		'q' => 'q',
	),
	array(
		'u' => '4-shared.eu',
		'q' => 'q',
	),
	array(
		'u' => 'runber.com',
		'q' => 'q',
	),
	array(
		'u' => 'claro-search.com',
		'q' => 'q',
	),
	array(
		'u' => 'univision.com',
		'q' => 'query',
	),
	array(
		'u' => 'fastaddressbar.com',
		'q' => 's',
	),
	array(
		'u' => 'delta-search.com',
		'q' => 'q',
	),
	array(
		'u' => 'blekko.com',
		'q' => 'q',
	),
	array(
		'u' => 'start.funmoods.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.icq.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.softonic.com',
		'q' => 'q',
	),
	array(
		'u' => 'find.tdc.dk',
		'q' => 'q',
	),
	array(
		'u' => 'm.tdc.dk',
		'q' => 'googleq',
	),
	array(
		'u' => 'aolsearch.com',
		'q' => 'q',
	),
	array(
		'u' => 'lycos.com',
		'q' => 'q',
	),
	array(
		'u' => 'jubii.dk',
		'q' => 'q',
	),
	array(
		'u' => 'genieo.com',
		'q' => 'q',
	),
	array(
		'u' => 'incredimail.com',
		'q' => 'q',
	),
	array(
		'u' => 'chatzum.com',
		'q' => 'q',
	),
	array(
		'u' => 'yahoo.',
		'q' => 'p',
	),
	array(
		'u' => 'myhealthcare.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.mywebsearch.com',
		'q' => 'searchfor',
	),
	array(
		'u' => 'search.creativetoolbars.com',
		'q' => 'q',
	),
	array(
		'u' => 'plusnetwork.com',
		'q' => 'q',
	),
	array(
		'u' => '29searchengines.com',
		'q' => 'q',
	),
	array(
		'u' => '30searchengines.com',
		'q' => 'q',
	),
	array(
		'u' => '90searchengines.com',
		'q' => 'q',
	),
	array(
		'u' => 'buscador.terra.com',
		'q' => 'query',
	),
	array(
		'u' => 'map.krak.dk',
		'q' => 'search_word',
	),
	array(
		'u' => 'firma.eniro.dk',
		'q' => 'search_word',
	),
	array(
		'u' => 'eniro.dk',
		'q' => 'search_word',
	),
	array(
		'u' => 'search.speedfox.me',
		'q' => 'q',
	),
	array(
		'u' => 'hotbot.com',
		'q' => 'q',
	),
	array(
		'u' => 'about.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.eazel.com',
		'q' => 'q',
	),
	array(
		'u' => 'en.eazel.com',
		'q' => 'q',
	),
	array(
		'u' => 'valentine.com',
		'q' => 'q',
	),
	array(
		'u' => 'isesfeel.com',
		'q' => 'title',
	),
	array(
		'u' => 'busca.starmedia.com',
		'q' => 'buscar',
	),
	array(
		'u' => 'clikseguro.com',
		'q' => 'q',
	),
	array(
		'u' => 'elcoblogs.com',
		'q' => 'search',
	),
	array(
		'u' => 'tmob.search-help.com',
		'q' => 'search',
	),
	array(
		'u' => 'es.luna.tv',
		'q' => 'q',
	),
	array(
		'u' => 'buzzdock.com',
		'q' => 'q',
	),
	array(
		'u' => 'amazon.com',
		'q' => 'query',
	),
	array(
		'u' => 'amazon.es',
		'q' => 'query',
	),
	array(
		'u' => 'amazon.fr',
		'q' => 'query',
	),
	array(
		'u' => 'buscador.terra.cl',
		'q' => 'query',
	),
	array(
		'u' => 'buscar.terra.com.ar',
		'q' => 'query',
	),
	array(
		'u' => 'river4dwn.com',
		'q' => 'title',
	),
	array(
		'u' => 'infospace.com',
		'q' => 'q',
	),
	array(
		'u' => 'amazon.es',
		'q' => 'query',
	),
	array(
		'u' => 'start.toshiba.com',
		'q' => 'q',
	),
	array(
		'u' => 'webcrawler.com',
		'q' => 'q',
	),
	array(
		'u' => 'metacrawler.com',
		'q' => 'q',
	),
	array(
		'u' => 'start.facemoods.com',
		'q' => 'q',
	),
	array(
		'u' => 'excite.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.monstercrawler.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.smilebox.com',
		'q' => 'q',
	),
	array(
		'u' => 'home.gamesgofree.com',
		'q' => 's',
	),
	array(
		'u' => 'find-and-go.com',
		'q' => 'q',
	),
	array(
		'u' => 'inbox.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.searchcompletion.com',
		'q' => 'q',
	),
	array(
		'u' => 'startgoogle.startpagina.nl',
		'q' => 'q',
	),
	array(
		'u' => 'wow.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.earthlink.net',
		'q' => 'q',
	),
	array(
		'u' => 'statmyweb.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.atajitos.com',
		'q' => 'q',
	),
	array(
		'u' => 'arianna.libero.it',
		'q' => 'query',
	),
	array(
		'u' => 'govome.com',
		'q' => 'q',
	),
	array(
		'u' => 'gooofullsearch.com',
		'q' => 'q',
	),
	array(
		'u' => 'home.myplaycity.com',
		'q' => 's',
	),
	array(
		'u' => 'searchcanvas.com',
		'q' => 'q',
	),
	array(
		'u' => 'buscar.hispavista.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.snapdo.com',
		'q' => 'q',
	),
	array(
		'u' => 'ya.03compu.ru',
		'q' => 'query',
	),
	array(
		'u' => 'handycafe.com',
		'q' => 'q',
	),
	array(
		'u' => 'netzero.net',
		'q' => 'query',
	),
	array(
		'u' => 'globososo.com',
		'q' => 'q',
	),
	array(
		'u' => 'quebles.com',
		'q' => 'q',
	),
	array(
		'u' => 'start.funmoods.com',
		'q' => 'q',
	),
	array(
		'u' => 'babylon.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.comcast.net',
		'q' => 'q',
	),
	array(
		'u' => 'search.pch.com',
		'q' => 'q',
	),
	array(
		'u' => 'infospace.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.ultrasurf.us',
		'q' => 'q',
	),
	array(
		'u' => 'ittravel.info',
		'q' => 'q',
	),
	array(
		'u' => 'search.peoplepc.com',
		'q' => 'q',
	),
	array(
		'u' => 'cc.bingj.com',
		'q' => 'q',
	),
	array(
		'u' => 'holasearch.com',
		'q' => 'q',
	),
	array(
		'u' => 'univision.com',
		'q' => 'q',
	),
	array(
		'u' => 'websearch.com',
		'q' => 'q',
	),
	array(
		'u' => 'bgoog.com',
		'q' => 'q',
	),
	array(
		'u' => 'busca.uol.com.br',
		'q' => 'q',
	),
	array(
		'u' => '100searchengines.com',
		'q' => 'q',
	),
	array(
		'u' => 'alhea.com',
		'q' => 'q',
	),
	array(
		'u' => 'finddotcom.com',
		'q' => 'text',
	),
	array(
		'u' => 'find1friend.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.qone8.com',
		'q' => 'q',
	),
	array(
		'u' => 'alhea.com',
		'q' => 'q',
	),
	array(
		'u' => 'start.myplaycity.com',
		'q' => 's',
	),
	array(
		'u' => 'search.nation.com',
		'q' => 'q',
	),
	array(
		'u' => 'findhurtig.dk',
		'q' => 'q',
	),
	array(
		'u' => 'websearchinc.net',
		'q' => 'query',
	),
	array(
		'u' => 'valentine.com',
		'q' => 'q',
	),
	array(
		'u' => 'overskrift.dk',
		'q' => 'q',
	),
	array(
		'u' => 'info.com',
		'q' => 'qkw',
	),
	array(
		'u' => 'mysearchdial.com',
		'q' => 'q',
	),
	array(
		'u' => 'mysearchdial.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.whitesmoke.com',
		'q' => 'q',
	),
	array(
		'u' => 'suche.aol.de',
		'q' => 'q',
	),
	array(
		'u' => 'duckduckgo.com',
		'q' => 'q',
	),
	array(
		'u' => 'mysearchdial.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.fluxbee.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.imesh.com',
		'q' => 'q',
	),
	array(
		'u' => 'msn.com',
		'q' => 'q',
	),
	array(
		'u' => 'mynet.com',
		'q' => 'query',
	),
	array(
		'u' => 'thiv.net',
		'q' => 'q',
	),
	array(
		'u' => 'zapmeta.dk',
		'q' => 'q',
	),
	array(
		'u' => 'suche.t-online.de',
		'q' => 'q',
	),
	array(
		'u' => 'degulesider.dk',
		'q' => 'search_word',
	),
	array(
		'u' => 'search.bt.com',
		'q' => 'p',
	),
	array(
		'u' => 'lasaoren.com',
		'q' => 'q',
	),
	array(
		'u' => 'indexa.fr',
		'q' => 'chaine',
	),
	array(
		'u' => 'sharelook.fr',
		'q' => 'keyword',
	),
	array(
		'u' => 'lemoteur.ke.voila.fr',
		'q' => 'kw',
	),
	array(
		'u' => 'astromenda.com',
		'q' => 'q',
	),
	array(
		'u' => 'soeg.jubii.dk',
		'q' => 'q',
	),
	array(
		'u' => 'speedial.com',
		'q' => 'q',
	),
	array(
		'u' => 'buenosearch.com',
		'q' => 'q',
	),
	array(
		'u' => 'search-results.mobi',
		'q' => 'q',
	),
	array(
		'u' => 'dba.dk',
		'q' => 'soeg',
	),
	array(
		'u' => 'sweetpacks-search.com',
		'q' => 'soeg',
	),
	array(
		'u' => 'searchgol.com',
		'q' => 'q',
	),
	array(
		'u' => 'avantbrowser.com',
		'q' => 'q',
	),
	array(
		'u' => 'golsearch.com',
		'q' => 'q',
	),
	array(
		'u' => 'crawler.com',
		'q' => 'q',
	),
	array(
		'u' => '43searchengines.com',
		'q' => 'q',
	),
	array(
		'u' => 'searches.qone8.com',
		'q' => 'q',
	),
	array(
		'u' => 'zapmeta.mx',
		'q' => 'q',
	),
	array(
		'u' => 'findwide.com',
		'q' => 'k',
	),
	array(
		'u' => 'virgilio.it',
		'q' => 'qrs',
	),
	array(
		'u' => 'quick-seeker.com',
		'q' => 'q',
	),
	array(
		'u' => 'esmuy.co',
		'q' => 'query',
	),
	array(
		'u' => 'search.smartshopping.com',
		'q' => 'keywords',
	),
	array(
		'u' => 'mysearch.com',
		'q' => 'q',
	),
	array(
		'u' => 'parverts.xyz',
		'q' => 'q',
	),
	array(
		'u' => 'bestsearch.space',
		'q' => 'q',
	),
	array(
		'u' => 'internationalnewsportal.com',
		'q' => 'q',
	),
	array(
		'u' => 'monsear.xyz',
		'q' => 'q',
	),
	array(
		'u' => 'parallaxsearch.com',
		'q' => 'qs',
	),
	array(
		'u' => 'newpage16.site',
		'q' => 'q',
	),
	array(
		'u' => 'maindom.xyz',
		'q' => 'q',
	),
	array(
		'u' => 'dealwifi.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.mail.com',
		'q' => 'q',
	),
	array(
		'u' => 'kvikstart.dk',
		'q' => 'q',
	),
	array(
		'u' => 'localmoxie.com',
		'q' => 'keyword',
	),
	array(
		'u' => 'lenovo.com',
		'q' => 'q',
	),
	array(
		'u' => 'gigablast.com',
		'q' => 'q',
	),
	array(
		'u' => 'faroo.com',
		'q' => 'q',
	),
	array(
		'u' => 'dmoz.org',
		'q' => 'q',
	),
	array(
		'u' => 'esmuy.es',
		'q' => 'query',
	),
	array(
		'u' => 'qwant.com',
		'q' => 'q',
	),
	array(
		'u' => 'start.fyi',
		'q' => 'q',
	),
	array(
		'u' => 'tabs000.online',
		'q' => 'q',
	),
	array(
		'u' => 'sosodesktop.com',
		'q' => 'q',
	),
	array(
		'u' => 'mplore.com',
		'q' => 'q',
	),
	array(
		'u' => 'searchturbo.com',
		'q' => 'q',
	),
	array(
		'u' => 'fvpimageviewer.com/search/',
		'q' => 'q',
	),
	array(
		'u' => 'tcl.start.fyi',
		'q' => 'q',
	),
	array(
		'u' => 'when.com',
		'q' => 'q',
	),
	array(
		'u' => 'esmuy.mx',
		'q' => 'query',
	),
	array(
		'u' => 'search.myway.com',
		'q' => 'searchfor',
	),
	array(
		'u' => 'esmuy.be',
		'q' => 'query',
	),
	array(
		'u' => 'palikan.com',
		'q' => 'q',
	),
	array(
		'u' => 'searchlock.com',
		'q' => 'q',
	),
	array(
		'u' => 'portalne.ws',
		'q' => 'q',
	),
	array(
		'u' => 'searchthe.website',
		'q' => 'q',
	),
	array(
		'u' => 'enhanced-search.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.navegaki.com',
		'q' => 'searchfor',
	),
	array(
		'u' => 'search.socialdownloadr.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.orbitum.com',
		'q' => 'search',
	),
	array(
		'u' => 'search.sidecubes.com',
		'q' => 'q',
	),
	array(
		'u' => 'blackle.com',
		'q' => 'q',
	),
	array(
		'u' => 'beaucoup.com',
		'q' => 'q',
	),
	array(
		'u' => 'searchpaw.com',
		'q' => 'q',
	),
	array(
		'u' => 'thesmartsearch.net',
		'q' => 'q',
	),
	array(
		'u' => 'airfind.com',
		'q' => 'search_term',
	),
	array(
		'u' => 'toshiba.com',
		'q' => 'q',
	),
	array(
		'u' => 'apps-search.com',
		'q' => 'q',
	),
	array(
		'u' => 'lifewireless.mobi',
		'q' => 'search_term',
	),
	array(
		'u' => 'searchprivacy.co',
		'q' => 'q',
	),
	array(
		'u' => 'searchthis.com',
		'q' => 'q',
	),
	array(
		'u' => 'searchalot.com',
		'q' => 'q',
	),
	array(
		'u' => 'hypersonica.com',
		'q' => 'q',
	),
	array(
		'u' => 'esmuy.fr',
		'q' => 'query',
	),
	array(
		'u' => 'kidzsearch.com',
		'q' => 'q',
	),
	array(
		'u' => 'appspot.com',
		'q' => 'q',
	),
	array(
		'u' => 'fiostrending.verizon.com',
		'q' => 'q',
	),
	array(
		'u' => 'sp-web.search.auone.jp',
		'q' => 'q',
	),
	array(
		'u' => 'zdsearch.com',
		'q' => 'sbq',
	),
	array(
		'u' => 'searchall.com',
		'q' => 'q',
	),
	array(
		'u' => 'centurylink.net',
		'q' => 'q',
	),
	array(
		'u' => 'kidrex.org',
		'q' => 'q',
	),
	array(
		'u' => 'searchguide.windstream.net',
		'q' => 'q',
	),
	array(
		'u' => 'portal.tds.net',
		'q' => 'q',
	),
	array(
		'u' => 'websearchne.ws',
		'q' => 'q',
	),
	array(
		'u' => 'izito.com',
		'q' => 'q',
	),
	array(
		'u' => 'izito.us',
		'q' => 'q',
	),
	array(
		'u' => 'midco.net',
		'q' => 'q',
	),
	array(
		'u' => 'att.net',
		'q' => 'q',
	),
	array(
		'u' => 'zapmeta.ws',
		'q' => 'q',
	),
	array(
		'u' => 'search.smt.docomo.ne.jp',
		'q' => 'MT',
	),
	array(
		'u' => 'icafemanager.com',
		'q' => 'q',
	),
	array(
		'u' => 'dudley.libnet.info',
		'q' => 'q',
	),
	array(
		'u' => 'wowway.net',
		'q' => 'q',
	),
	array(
		'u' => 'start.iminent.com',
		'q' => 'q',
	),
	array(
		'u' => 'virginmedia.com',
		'q' => 'q',
	),
	array(
		'u' => 'internet-start.net',
		'q' => 'q',
	),
	array(
		'u' => 'queens.libnet.info',
		'q' => 'q',
	),
	array(
		'u' => 'shinysearch.com',
		'q' => 'q',
	),
	array(
		'u' => 'informationvine.com',
		'q' => 'q',
	),
	array(
		'u' => 'startpagina.nl',
		'q' => 'query',
	),
	array(
		'u' => 'laban.vn',
		'q' => 'q',
	),
	array(
		'u' => 'mytelus.telus.ca',
		'q' => 'q',
	),
	array(
		'u' => 'de.dolphin.com',
		'q' => 'q',
	),
	array(
		'u' => 'frontpage.pch.com',
		'q' => 'q',
	),
	array(
		'u' => 'virginmedia.com',
		'q' => 'q',
	),
	array(
		'u' => 'cox.com',
		'q' => 'term',
	),
	array(
		'u' => 'safesearchkids.com',
		'q' => 'q',
	),
	array(
		'u' => 'surreylibraries.libnet.info',
		'q' => 'q',
	),
	array(
		'u' => 'intoautos.com',
		'q' => 'q',
	),
	array(
		'u' => 'telstra.com.au',
		'q' => 'find',
	),
	array(
		'u' => 'search.f-secure.com',
		'q' => 'query',
	),
	array(
		'u' => 'finderoo.com',
		'q' => 'q',
	),
	array(
		'u' => 'alothome.com',
		'q' => 'slk',
	),
	array(
		'u' => 'searchguide.level3.com',
		'q' => 'q',
	),
	array(
		'u' => 'mediacomcable.com',
		'q' => 'q',
	),
	array(
		'u' => 'windstream.net',
		'q' => 'q',
	),
	array(
		'u' => 'three.co.uk',
		'q' => 'q',
	),
	array(
		'u' => 'armstrongmywire.com',
		'q' => 'q',
	),
	array(
		'u' => 'coccoc.com',
		'q' => 'query',
	),
	array(
		'u' => 'atlanticbb.net',
		'q' => 'q',
	),
	array(
		'u' => 'cincinnatibell.net',
		'q' => 'q',
	),
	array(
		'u' => 'refseek.com',
		'q' => 'q',
	),
	array(
		'u' => 'mediacomtoday.com',
		'q' => 'q',
	),
	array(
		'u' => 'uscellular.com',
		'q' => 'q',
	),
	array(
		'u' => 'fenrir-inc.com',
		'q' => 'q',
	),
	array(
		'u' => 'fulltabsearch.com',
		'q' => 'q',
	),
	array(
		'u' => 'easysearch.org.uk',
		'q' => 's',
	),
	array(
		'u' => 'scienceforums.com',
		'q' => 'q',
	),
	array(
		'u' => 'yasp.no',
		'q' => 'q',
	),
	array(
		'u' => 'zapmeta.co.uk',
		'q' => 'query',
	),
	array(
		'u' => 'pancafepro.com',
		'q' => 'q',
	),
	array(
		'u' => 'zeelandnet.nl',
		'q' => 'q',
	),
	array(
		'u' => 'findblogs.com',
		'q' => 'q',
	),
	array(
		'u' => 'billigpris.eu',
		'q' => 'q',
	),
	array(
		'u' => 'shawconnect.ca',
		'q' => 'q',
	),
	array(
		'u' => 'startjuno.com',
		'q' => 'q',
	),
	array(
		'u' => 'goodskins.com',
		'q' => 'q',
	),
	array(
		'u' => 'zapmeta.com',
		'q' => 'q',
	),
	array(
		'u' => 'ok.hu',
		'q' => 'q',
	),
	array(
		'u' => 'emailaccountlogin.co',
		'q' => 'search',
	),
	array(
		'u' => 'faltradforum.de',
		'q' => 'keywords',
	),
	array(
		'u' => 'shopalike.dk',
		'q' => 'k',
	),
	array(
		'u' => 'izito.dk',
		'q' => 'q',
	),
	array(
		'u' => 'izito.info',
		'q' => 'query',
	),
	array(
		'u' => 'vosteran.com',
		'q' => 'q',
	),
	array(
		'u' => 'findarticles.com',
		'q' => 'sbq',
	),
	array(
		'u' => 'straighttalk.com',
		'q' => 'search_term',
	),
	array(
		'u' => 'searchassist.verizon.com',
		'q' => 'SearchQuery',
	),
	array(
		'u' => 'yoursearch.me',
		'q' => 'q',
	),
	array(
		'u' => 'savevy.com',
		'q' => 'q',
	),
	array(
		'u' => 'epicsearch.in',
		'q' => 'q',
	),
	array(
		'u' => 'googsearch.us',
		'q' => 'q',
	),
	array(
		'u' => 'sendearnings.com',
		'q' => 'query',
	),
	array(
		'u' => 'youtube.com',
		'q' => 'search_query',
	),
	array(
		'u' => 'epicsearch.in',
		'q' => 'q',
	),
	array(
		'u' => 'finder.cox.net',
		'q' => 'SearchQuery',
	),
	array(
		'u' => 'search.frontier.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.lilo.org',
		'q' => 'q',
	),
	array(
		'u' => 'findarticles.com',
		'q' => 'sbq',
	),
	array(
		'u' => 'sm.de',
		'q' => 'q',
	),
	array(
		'u' => 'reference.com',
		'q' => 'q',
	),
	array(
		'u' => 'slickdeals.net',
		'q' => 'q',
	),
	array(
		'u' => 'only-search.com',
		'q' => 'q',
	),
	array(
		'u' => 'images.rambler.ru',
		'q' => 'query',
	),
	array(
		'u' => 'forum.slowtwitch.com',
		'q' => 'search_string',
	),
	array(
		'u' => 'internet.tre.it',
		'q' => 'q',
	),
	array(
		'u' => 'zapmeta.it',
		'q' => 'q',
	),
	array(
		'u' => 'search.fdownloadr.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.findeer.com',
		'q' => 'q',
	),
	array(
		'u' => 'www.izito.it',
		'q' => 'query',
	), // ref: https://www.izito.it/c?u=https%3A%2F%2Fcleverplugins.com%2Fblog%2F
	array(
		'u' => 'myepb.net',
		'q' => 'q',
	),
	array(
		'u' => 'botw.org',
		'q' => 'SearchTerm',
	),
	array(
		'u' => 'lukol.com',
		'q' => 'q',
	),
	array(
		'u' => 'dregol.com',
		'q' => 'q',
	),
	array(
		'u' => 'ampxsearch.com',
		'q' => 's',
	),
	array(
		'u' => 'pacificair.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.xfinity.com',
		'm' => '|search.xfinity.com/#web/(.*)/1|i',
	),
	array(
		'u' => 'search.twcc.com',
		'm' => '|search.twcc.com/#web/(.*)/1|i',
	),
	array(
		'u' => 'pinterest.de/search',
		'q' => 'q',
	),
	array(
		'u' => 'search.zum.com',
		'q' => 'query',
	),
	array(
		'u' => 'easybib.com/cite/results',
		'q' => 'query',
	),
	array(
		'u' => 'gaasearch.us',
		'q' => 'q',
	),
	array(
		'u' => 'search.1and1.com',
		'q' => 'q',
	),
	array(
		'u' => 'rockettab.com',
		'q' => 'kwd',
	),
	array(
		'u' => 'wikispaces.com',
		'm' => '|.wikispaces.com/(.*)|i',
	),
	array(
		'u' => 'amazon.it',
		'q' => 'keywords',
	),
	array(
		'u' => 'ya.ru',
		'q' => 'q',
	),
	array(
		'u' => 'lonesearch.com',
		'q' => 'q',
	),
	array(
		'u' => 'evanscycles.com',
		'q' => 'esvq',
	),
	array(
		'u' => 'krak.dk',
		'm' => '|.krak.dk/(.*)/s%c3%b8g.cs|i',
	),
	array(
		'u' => 'bikeradar.com',
		'q' => 'searchterm',
	),
	array(
		'u' => 'zapmeta.es',
		'q' => 'q',
	),
	array(
		'u' => 'shotdeadinthehead.com',
		'q' => 'keywords',
	),
	array(
		'u' => 'lazta.com',
		'q' => 'q',
	),
	array(
		'u' => 'weloveshopping.com',
		'q' => 'q',
	),
	array(
		'u' => 'roomba-search.com',
		'q' => 'query',
	),
	array(
		'u' => 'excite.co.uk',
		'q' => 'q',
	),
	array(
		'u' => 'bildungsservice.lesestoff.ch',
		'q' => 'bpmquery',
	),
	array(
		'u' => 'mencap.org.uk',
		'q' => 'q',
	),
	array(
		'u' => 'gopher.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.totalav.com',
		'q' => 'q',
	),
	array(
		'u' => 'netdeals.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.handy-tab.com',
		'q' => 'q',
	),
	array(
		'u' => 'www.linkedin.com/search/results/',
		'q' => 'keywords',
	),
	array(
		'u' => 'suche.gmx.net',
		'q' => 'q',
	),
	array(
		'u' => 'search.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.selfbutler.com',
		'q' => 'utm_term',
	),
	array(
		'u' => 'searchnow.com',
		'q' => 'q',
	),
	array(
		'u' => 'coinup.org',
		'q' => 'q',
	),
	array(
		'u' => 'smarter.com',
		'q' => 'q',
	),
	array(
		'u' => 'zapmeta.uk',
		'q' => 'q',
	),
	array(
		'u' => 'comparing.xyz',
		'q' => 'q',
	),
	array(
		'u' => 'www.krak.dk',
		'q' => 'search_word',
	),
	array(
		'u' => 'seekkees.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.uselilo.org',
		'q' => 'q',
	),
	array(
		'u' => 'cableone.net',
		'q' => 'q',
	),
	array(
		'u' => 'juniorsafesearch.com',
		'q' => 'q',
	),
	array(
		'u' => 'linksdk.dk',
		'q' => 'phrase',
	),
	array(
		'u' => 'dspace.bcucluj.ro',
		'q' => 'filterquery',
	),
	array(
		'u' => 'app.buzzsumo.com',
		'q' => 'q',
	),
	array(
		'u' => 'amino.dk/search/searchresults.aspx',
		'q' => 'q',
	),
	array(
		'u' => 'app.kwfinder.com',
		'q' => 'keyword',
	),
	array(
		'u' => 'search.clearch.org',
		'q' => 'q',
	),
	array(
		'u' => 'elatar.com',
		'q' => 'q',
	),
	array(
		'u' => 'freaktab.com/search',
		'q' => 'q',
	),
	array(
		'u' => 'idb.buzzstream.com',
		'q' => 'q',
	),
	array(
		'u' => 'yandex.com.tr/search/',
		'q' => 'text',
	),
	array(
		'u' => 'search.xfinity.com',
		'q' => 'searchterm',
	),
	array(
		'u' => 'inboxdollars.com',
		'q' => 'query',
	),
	array(
		'u' => 'quafind.com',
		'q' => 'q',
	),
	array(
		'u' => 'privatebrowsing.com',
		'q' => 'q',
	),
	array(
		'u' => 'navigationshilfe.t-online.de',
		'q' => 'q',
	),
	array(
		'u' => 'nav-goo.com',
		'q' => 'q',
	),
	array(
		'u' => 'searx.dk',
		'q' => 'q',
	),
	array(
		'u' => 'xfinity.com',
		'q' => 'searchterm',
	),
	array(
		'u' => 'ordissinaute.fr',
		'q' => 'q',
	),
	array(
		'u' => 'elatar.com',
		'q' => 'q',
	),
	array(
		'u' => 'suddenlink.net',
		'q' => 'q',
	),
	array(
		'u' => 'zapmetasearch.com',
		'q' => 'q',
	),
	array(
		'u' => 'search-story.com',
		'q' => 'q',
	),
	array(
		'u' => 'gibiru.com',
		'q' => 'q',
	),
	array(
		'u' => 'jobindex.dk',
		'q' => 'q',
	),
	array(
		'u' => 'overdrive.com',
		'q' => 'q',
	),
	array(
		'u' => 'kysy.com',
		'q' => 'q',
	),
	array(
		'u' => 'froogle.ihyd.com',
		'q' => 'q',
	),
	array(
		'u' => 'wibki.com',
		'q' => 'q',
	),
	array(
		'u' => 'zapmeta.fr',
		'q' => 'q',
	),
	array(
		'u' => 'zapmeta.pt',
		'q' => 'q',
	),
	array(
		'u' => 'zoeken.nl',
		'q' => 'q',
	),
	array(
		'u' => 'startxxl.com',
		'q' => 'q',
	),
	array(
		'u' => 'yandex.ru',
		'q' => 'text',
	),
	array(
		'u' => 'search.avira.com',
		'q' => 'text',
	),
	array(
		'u' => 'neuvoo.com',
		'q' => 'q',
	),
	array(
		'u' => 'neuvoo.fr',
		'q' => 'q',
	),
	array(
		'u' => 'neuvoo.be',
		'q' => 'q',
	),
	array(
		'u' => 'areaguides.net',
		'q' => 'what',
	),
	array(
		'u' => 'safesearch.net',
		'm' => '|.safesearch.net/(.*)|i',
	),
	array(
		'u' => 'pronto.com',
		'q' => 'q',
	),
	array(
		'u' => 'sploshuk.co.uk/forum/',
		'q' => 'keywords',
	),
	array(
		'u' => 'amazon.de',
		'q' => 'query',
	),
	array(
		'u' => 'start.me',
		'q' => 'q',
	),
	array(
		'u' => 'tf.xtopoly.com',
		'q' => 'keyword',
	),
	array(
		'u' => 'lipstickfetish.org',
		'q' => 'keywords',
	),
	array(
		'u' => 'search.juno.com',
		'q' => 'query',
	),
	array(
		'u' => 'search.nifty.com',
		'q' => 'text',
	),
	array(
		'u' => 'search.orange.co.uk',
		'q' => 'q',
	),
	array(
		'u' => 'sp-search.auone.jp',
		'q' => 'q',
	),
	array(
		'u' => 'charter.net',
		'q' => 'q',
	),
	array(
		'u' => 'myconsolidated.net',
		'q' => 'q',
	),
	array(
		'u' => 'doko-search.com',
		'q' => 'q',
	),
	array(
		'u' => '33searchengines.com',
		'q' => 'q',
	),
	array(
		'u' => '70searchengines.com',
		'q' => 'q',
	),
	array(
		'u' => 'lookany.com',
		'm' => '|.lookany.com/search/(.*)|i',
	),
	array(
		'u' => 'search.foxtab.com',
		'q' => 'q',
	),
	array(
		'u' => 'istart.webssearches.com',
		'q' => 'q',
	),
	array(
		'u' => 'searchqu.com',
		'q' => 'q',
	),
	array(
		'u' => 'kereso.startlap.hu',
		'q' => 'q',
	),
	array(
		'u' => 'ronstrand.com',
		'q' => 'q',
	),
	array(
		'u' => 'amazon.co.uk',
		'q' => 'query',
	),
	array(
		'u' => 'carrot2.org/stable/search',
		'q' => 'query',
	),
	array(
		'u' => '41searchengines.com',
		'q' => 'q',
	),
	array(
		'u' => 'mycenturylink.com',
		'q' => 'q',
	),
	array(
		'u' => 'charter.net',
		'q' => 'q',
	),
	array(
		'u' => 'suche.gmx.ch',
		'q' => 'q',
	),
	array(
		'u' => 'busca.ya.com',
		'q' => 'q',
	),
	array(
		'u' => 'searchbrowsing.com',
		'q' => 'q',
	),
	array(
		'u' => 'buckeyecablesystem.net',
		'q' => 'q',
	),
	array(
		'u' => 'startpage.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.twcc.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.bbc.co.uk/search',
		'q' => 'q',
	),
	array(
		'u' => 'searchincognito.com',
		'q' => 'q',
	),
	array(
		'u' => 'searchpage-results.net',
		'q' => 'q',
	),
	array(
		'u' => 'safe.search.tools/search',
		'q' => 'q',
	),
	array(
		'u' => 'search-new.com',
		'q' => 'q',
	),
	array(
		'u' => '118.dk',
		'q' => 'what',
	),
	array(
		'u' => 'infobel.com',
		'q' => 'q',
	),
	array(
		'u' => 'wine-searcher.com',
		'm' => '|wine-searcher.com/find/(.*)|i',
	),
	array(
		'u' => 'jobnet.dk',
		'q' => 'searchstring',
	),
	array(
		'u' => 'searchbrowsing.com',
		'q' => 'q',
	),
	array(
		'u' => 'myprivatesearch.com',
		'q' => 'q',
	),
	array(
		'u' => 'findgofind.org',
		'q' => 'q',
	),
	array(
		'u' => 'boost.ur-search.com',
		'q' => 'q',
	),
	array(
		'u' => 'securesearch.co',
		'q' => 'q',
	),
	array(
		'u' => 'search.tut.by',
		'q' => 'query',
	),
	array(
		'u' => 'boost.ur-search.com/search',
		'q' => 'qq',
	), // Keyword never provided - just for traffic tracking
	array(
		'u' => 'securesearch.co/',
		'q' => 'qq',
	), // Keyword never provided - just for traffic tracking
	//3.4.5
	array(
		'u' => 'zapmeta.co.in',
		'q' => 'q',
	),
	array(
		'u' => 'g.results.supply',
		'q' => 'q',
	),
	array(
		'u' => 'search.trustnav.com',
		'q' => 'q',
	),
	array(
		'u' => 'homeandgardenideas.com',
		'q' => 'q',
	),
	array(
		'u' => 'newtab.club',
		'q' => 'q',
	),
	array(
		'u' => 'vinden.nl',
		'q' => 'q',
	),
	array(
		'u' => 'searchingdog.com',
		'q' => 'q',
	),
	array(
		'u' => 'get.tv.com',
		'q' => 'q',
	),
	array(
		'u' => 'izito.co.in',
		'q' => 'q',
	),
	//3.4.6
	array(
		'u' => 'zlsite.com',
		'q' => 'wd',
	),
	array(
		'u' => 'gosearchresults.com',
		'q' => 'q',
	),
	//3.4.7
	array(
		'u' => 'bravesearch.net',
		'q' => 'wd',
	),
	array(
		'u' => 'hao123.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.gmx.com',
		'q' => 'qq',
	), // Keyword never provided - just for traffic tracking
	array(
		'u' => 'ecosia.org',
		'q' => 'q',
	),
	array(
		'u' => 'so.m.sm.cn',
		'q' => 'q',
	),
	// 3.5
	array(
		'u' => 'zapmeta.ca',
		'q' => 'query',
	),
	array(
		'u' => 'alarms.org',
		'q' => 'q',
	),
	array(
		'u' => 'symbaloo.com',
		'q' => 'q',
	),
	array(
		'u' => 'perfectpixel.de',
		'q' => 'q',
	),
	array(
		'u' => 'www.izito.ws',
		'q' => 'q',
	),
	array(
		'u' => 'search.azkware.net',
		'q' => 'q',
	),
	array(
		'u' => 'searx.openhoofd.nl',
		'q' => 'q',
	),
	array(
		'u' => 'izito.co.uk',
		'q' => 'q',
	),
	array(
		'u' => 'kensaq.com',
		'q' => 'q',
	),
	array(
		'u' => 'surf-es.com',
		'q' => 'q',
	),
	array(
		'u' => 'browse-go.com',
		'q' => 'q',
	),
	array(
		'u' => 'kadaza.dk',
		'q' => 'q',
	),
	array(
		'u' => 'browse-go.com',
		'q' => 'q',
	),
	array(
		'u' => 'kadaza.nl',
		'q' => 'q',
	),
	array(
		'u' => 'decanter.com',
		'q' => 'name',
	),
	array(
		'u' => 'www.symbaloo.com',
		'q' => 'q',
	),
	array(
		'u' => 'izito.co.uk',
		'q' => 'q',
	),
	array(
		'u' => 'iseek.com',
		'q' => 'q',
	),
	array(
		'u' => 'search-api.co',
		'q' => 'q',
	),
	array(
		'u' => 'www.kensaq.com',
		'q' => 'q',
	),
	array(
		'u' => 'searx.aquilenet.fr',
		'q' => 'q',
	),
	array(
		'u' => 'igoogleportal.com',
		'q' => 'q',
	),
	array(
		'u' => 'surf-es.com',
		'q' => 'q',
	),
	array(
		'u' => 'search.azby.fmworld.net',
		'q' => 'q',
	),
	array(
		'u' => 'yz.m.sm.cn',
		'q' => 'q',
	),
	array(
		'u' => 'uspo.xyz',
		'q' => 'wd',
	),
	array(
		'u' => 'swisscows.com',
		'q' => 'query',
	),
	array(
		'u' => 'kensaq.com',
		'q' => 'q',
	),
	// 3.5.2
	array(
		'u' => 'instasrch.com',
		'q' => 'search_term',
	),
	array(
		'u' => 'betabuzz.com/search',
		'q' => 'q',
	),
	array(
		'u' => 'wbsrch.com',
		'q' => 'q',
	),
	// 3.5.4
	array(
		'u' => 'kafe.co.il',
		'q' => 'search',
	),
	array(
		'u' => 'yz.m.sm.cn',
		'q' => 'q',
	),
	array(
		'u' => 'search.azby.fmworld.net',
		'q' => 'q',
	),
	array(
		'u' => 'isearch.omiga-plus.com',
		'q' => 'q',
	),
	array(
		'u' => 'webalta.ru',
		'q' => 'q',
	),
	array(
		'u' => 'top-page.ru',
		'q' => 'q',
	),
	array(
		'u' => 'findgala.com',
		'q' => 'q',
	),
	array(
		'u' => 'explorary.com',
		'q' => 'q',
	),
	array(
		'u' => 'secure-surf.com',
		'q' => 'q',
	),
	array(
		'u' => 'website-unavailable.com',
		'q' => 'q',
	),
	array(
		'u' => 'sapo.pt',
		'q' => 'q',
	),
	array(
		'u' => 'zoo.com',
		'q' => 'q',
	),
	array(
		'u' => 'mundo.com',
		'q' => 'q',
	),
	array(
		'u' => '66searchengines.com',
		'q' => 'q',
	),
	array(
		'u' => '39searchengines.com',
		'q' => 'q',
	),
	array(
		'u' => 'schnell-startseite.de',
		'q' => 'q',
	),
	array(
		'u' => '.die-startseite.net',
		'q' => 'q',
	),
	array(
		'u' => 'excite.es',
		'q' => 'q',
	),
	array(
		'u' => 'search.surfcanyon.com',
		'q' => 'q',
	),
	array(
		'u' => 'sindice.com',
		'q' => 'q',
	),
	array(
		'u' => 'searchresults.verizon.com',
		'q' => 'q',
	),
	array(
		'u' => 'uk20.co.uk',
		'q' => 'q',
	),
	array(
		'u' => 'swagbucks.com',
		'q' => 'q',
	),
	array(
		'u' => '22searchengines.com',
		'q' => 'q',
	),
	array(
		'u' => 'hallar.es',
		'q' => 'q',
	),
	array(
		'u' => 'aonde.com.br',
		'q' => 'q',
	),
	array(
		'u' => 'liveinternet.ru',
		'q' => 'q',
	),
	array(
		'u' => 'search.gophoto.it',
		'q' => 'q',
	),
	array(
		'u' => 'pesquisa.sapo.pt',
		'q' => 'q',
	),
	array(
		'u' => 'recherche.aol.fr',
		'q' => 'q',
	),
	array(
		'u' => 'searx.ch',
		'q' => 'q',
	),
	array(
		'u' => 'm.yz.sm.cn',
		'q' => 'q',
	),
	array(
		'u' => 'yandex.kz',
		'q' => 'text',
	),

	// 3.5.11
		array(
			'u' => 'searx.ch',
			'q' => 'q',
		),
	array(
		'u' => 'ankiro.dk',
		'q' => 'q',
	),
	array(
		'u' => 'flysrch.com',
		'q' => 'search_term',
	),
	array(
		'u' => 'my-search.site',
		'q' => 'q',
	),
	array(
		'u' => 'zapmeta.hk',
		'q' => 'q',
	),
	array(
		'u' => 'facemojikeyboard.com',
		'q' => 'q',
	),
	// 3.5.21
		array(
			'u' => 'handy-tab.com',
			'q' => 'q',
		),

	// 3.5.25
		array(
			'u' => 'yippy.com',
			'q' => 'query',
		),
	array(
		'u' => 'search-error.com',
		'q' => 'q',
	),
	array(
		'u' => 'handy-tab.com',
		'q' => 'q',
	),

	// 3.6.3
		array(
			'u' => 'twitter.com',
			'q' => 'q',
		),
	array(
		'u' => 'searchingstar.com',
		'q' => 'qc',
	),
	array(
		'u' => 'quark.sm.c',
		'q' => 'q',
	),
	array(
		'u' => 'search2.co',
		'q' => 'search_term',
	),
	array(
		'u' => 'maps.apple.com',
		'q' => 'q',
	),
	array(
		'u' => 'productopia.com',
		'q' => 'q',
	),

	/*
		 NEW:


https://www.productopia.com/shopping?qo=semquery&ad=sema&q=rental%20autos&o=785158&ag=fw5&an=msn_s&rch=intl657


		 https://maps.apple.com/place?address=holkebjergvej%2062%2c%205250%20odense%20sv%2c%20danmark&auid=14990575193637403193&ll=55.359064%2c10.326131&q=fyns%20undervognscenter
						array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
	array( 'u' =>'___', 'q' => '___'),
				   ****************************** IGNORE:
			  http://resellerportal.iupgrade01.ddintranet.dk/webshops?searchfor=zonex&nodes=3%2c6%2c7%2c8%2c9%2c10%2c11%2c12%2c13%2c14%2c15%2c16%2c17%2c18%2c20%2c22%2c23%2c24%2c25%2c26%2c......
			  ssl.ditonlinebetalingssystem.dk
		 online.seranking.com/admin.site.che...
		 http://boo.neuvoo.com/dash/
			  https://www.linkedin.com/messaging/*
	FROM https://www.linkedin.com/messaging/thread/6367371086828576768/
		 + https://app.accuranker.com/keywords/list/workingmediadk/fyuncedk-2/
					  https://vancouver.ca/map.aspx?q=453+west+12th+ave,+vancouver,+bc+v5k+1v4,+canada
			*/

			array(
				'u' => 'google.',
				'q' => 'q',
			), // Here collects the rest Google. tlds
	array(
		'u' => 'yellowpages.com',
		'q' => 'search_terms',
	),
); // FINAL ONE - no comma :-)

