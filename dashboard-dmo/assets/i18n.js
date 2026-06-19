'use strict';

const SVX_TRANSLATIONS = {
  en: {
    'Painel': 'Dashboard',
    'Administração': 'Admin',
    'Registos': 'Logs',
    'Hardware': 'Hardware',
    'Modo dia': 'Day mode',
    'Modo noite': 'Night mode',
    'Hora local:': 'Local time:',
    'Estado DMO': 'DMO state',
    'Saúde Raspberry/NUC': 'Raspberry/NUC health',
    'Alertas áudio/rádio': 'Audio/radio warnings',
    'Última mensagem rádio': 'Latest radio message',
    'Serviço SvxLink': 'SvxLink service',
    'Carga': 'Load',
    'Temp. CPU': 'CPU temp',
    'Memória': 'Memory',
    'Disco': 'Disk',
    'Áudio e PTT': 'Audio and PTT',
    'Ligação ao refletor': 'Reflector link',
    'SDS e utilizadores': 'SDS and users',
    'Utilizadores': 'Users',
    'Ouvidos': 'Heard',
    'Estados SDS': 'SDS states',
    'RSSI gateway': 'Gateway RSSI',
    'Actividade DMO': 'DMO activity',
    'Hora': 'Time',
    'Tipo': 'Type',
    'Estação': 'Station',
    'Mensagem': 'Message',
    'Sistema': 'System',
    'Nome': 'Name',
    'Arquitectura': 'Architecture',
    'Saúde do sistema': 'System health',
    'Alertas': 'Warnings',
    'Terminais': 'Mobiles',
    'Última vez': 'Last seen',
    'Sem terminais observados': 'No mobiles seen',
    'Sem eventos encontrados': 'No events found',
    'Sem actividade recente': 'No recent activity',
    'Indisponível': 'Unavailable',
    'Enviar SDS': 'Send SDS',
    'Destino ISSI/TSI': 'Destination ISSI/TSI',
    'Mensagem': 'Message',
    'Enviar': 'Send',
    'Limpar': 'Clear',
    'Caminho TetraLogic': 'TetraLogic path',
    'Modelos SDS': 'SDS presets',
    'Registo SDS': 'SDS log',
    'Entradas': 'Entries',
    'Consola PEI': 'PEI console',
    'Comando AT manual': 'Manual AT command',
    'Enviar comando': 'Send command',
    'Potência RF': 'RF power',
    'Aplicar potência': 'Apply power',
    'Registo PEI': 'PEI log',
    'Origem': 'Source',
    'Comando': 'Command',
    'Estado': 'Status',
    'Eventos interpretados': 'Parsed events',
    'Procurar': 'Search',
    'Filtrar': 'Filter',
    'Em directo': 'Live',
    'Total:': 'Total:',
    'Apagar': 'Delete',
    'Guardar modelo': 'Save preset',
    'Guardar palavra-passe': 'Save password',
    'ATIVO': 'ACTIVE',
    'INATIVO': 'INACTIVE',
    'FALHA': 'FAILED',
    'DESCONHECIDO': 'UNKNOWN',
    'PRONTO': 'READY',
    'LIGADO': 'CONNECTED',
    'EM BAIXO': 'DOWN',
    'EM ESPERA': 'IDLE'
  },
  fr: {
    'Painel': 'Tableau de bord',
    'Administração': 'Administration',
    'Registos': 'Journaux',
    'Hardware': 'Matériel',
    'Modo dia': 'Mode jour',
    'Modo noite': 'Mode nuit',
    'Hora local:': 'Heure locale :',
    'Estado DMO': 'État DMO',
    'Saúde Raspberry/NUC': 'Santé Raspberry/NUC',
    'Alertas áudio/rádio': 'Alertes audio/radio',
    'Última mensagem rádio': 'Dernier message radio',
    'Serviço SvxLink': 'Service SvxLink',
    'Carga': 'Charge',
    'Temp. CPU': 'Temp. CPU',
    'Memória': 'Mémoire',
    'Disco': 'Disque',
    'Áudio e PTT': 'Audio et PTT',
    'Ligação ao refletor': 'Lien réflecteur',
    'SDS e utilizadores': 'SDS et utilisateurs',
    'Utilizadores': 'Utilisateurs',
    'Ouvidos': 'Entendus',
    'Estados SDS': 'États SDS',
    'RSSI gateway': 'RSSI passerelle',
    'Actividade DMO': 'Activité DMO',
    'Hora': 'Heure',
    'Tipo': 'Type',
    'Estação': 'Station',
    'Mensagem': 'Message',
    'Sistema': 'Système',
    'Nome': 'Nom',
    'Arquitectura': 'Architecture',
    'Alertas': 'Alertes',
    'Terminais': 'Terminaux',
    'Última vez': 'Dernière fois',
    'Sem terminais observados': 'Aucun terminal observé',
    'Sem eventos encontrados': 'Aucun événement trouvé',
    'Sem actividade recente': 'Aucune activité récente',
    'Indisponível': 'Indisponible',
    'Enviar SDS': 'Envoyer SDS',
    'Destino ISSI/TSI': 'Destination ISSI/TSI',
    'Enviar': 'Envoyer',
    'Limpar': 'Effacer',
    'Caminho TetraLogic': 'Chemin TetraLogic',
    'Modelos SDS': 'Modèles SDS',
    'Registo SDS': 'Journal SDS',
    'Consola PEI': 'Console PEI',
    'Comando AT manual': 'Commande AT manuelle',
    'Enviar comando': 'Envoyer commande',
    'Potência RF': 'Puissance RF',
    'Aplicar potência': 'Appliquer puissance',
    'Registo PEI': 'Journal PEI',
    'Origem': 'Source',
    'Comando': 'Commande',
    'Estado': 'État',
    'Eventos interpretados': 'Événements interprétés',
    'Procurar': 'Rechercher',
    'Filtrar': 'Filtrer',
    'Em directo': 'En direct',
    'Total:': 'Total :',
    'Apagar': 'Supprimer',
    'Guardar modelo': 'Enregistrer modèle',
    'Guardar palavra-passe': 'Enregistrer mot de passe',
    'ATIVO': 'ACTIF',
    'INATIVO': 'INACTIF',
    'FALHA': 'ÉCHEC',
    'DESCONHECIDO': 'INCONNU',
    'PRONTO': 'PRÊT',
    'LIGADO': 'CONNECTÉ',
    'EM BAIXO': 'HORS LIGNE',
    'EM ESPERA': 'EN ATTENTE'
  },
  es: {
    'Painel': 'Panel',
    'Administração': 'Administración',
    'Registos': 'Registros',
    'Hardware': 'Hardware',
    'Modo dia': 'Modo día',
    'Modo noite': 'Modo noche',
    'Hora local:': 'Hora local:',
    'Estado DMO': 'Estado DMO',
    'Saúde Raspberry/NUC': 'Salud Raspberry/NUC',
    'Alertas áudio/rádio': 'Alertas audio/radio',
    'Última mensagem rádio': 'Último mensaje radio',
    'Serviço SvxLink': 'Servicio SvxLink',
    'Carga': 'Carga',
    'Temp. CPU': 'Temp. CPU',
    'Memória': 'Memoria',
    'Disco': 'Disco',
    'Áudio e PTT': 'Audio y PTT',
    'Ligação ao refletor': 'Enlace reflector',
    'SDS e utilizadores': 'SDS y usuarios',
    'Utilizadores': 'Usuarios',
    'Ouvidos': 'Escuchados',
    'Estados SDS': 'Estados SDS',
    'RSSI gateway': 'RSSI gateway',
    'Actividade DMO': 'Actividad DMO',
    'Hora': 'Hora',
    'Tipo': 'Tipo',
    'Estação': 'Estación',
    'Mensagem': 'Mensaje',
    'Sistema': 'Sistema',
    'Nome': 'Nombre',
    'Arquitectura': 'Arquitectura',
    'Alertas': 'Alertas',
    'Terminais': 'Terminales',
    'Última vez': 'Última vez',
    'Sem terminais observados': 'Sin terminales observados',
    'Sem eventos encontrados': 'Sin eventos encontrados',
    'Sem actividade recente': 'Sin actividad reciente',
    'Indisponível': 'No disponible',
    'Enviar SDS': 'Enviar SDS',
    'Destino ISSI/TSI': 'Destino ISSI/TSI',
    'Enviar': 'Enviar',
    'Limpar': 'Limpiar',
    'Caminho TetraLogic': 'Ruta TetraLogic',
    'Modelos SDS': 'Modelos SDS',
    'Registo SDS': 'Registro SDS',
    'Consola PEI': 'Consola PEI',
    'Comando AT manual': 'Comando AT manual',
    'Enviar comando': 'Enviar comando',
    'Potência RF': 'Potencia RF',
    'Aplicar potência': 'Aplicar potencia',
    'Registo PEI': 'Registro PEI',
    'Origem': 'Origen',
    'Comando': 'Comando',
    'Estado': 'Estado',
    'Eventos interpretados': 'Eventos interpretados',
    'Procurar': 'Buscar',
    'Filtrar': 'Filtrar',
    'Em directo': 'En directo',
    'Total:': 'Total:',
    'Apagar': 'Borrar',
    'Guardar modelo': 'Guardar modelo',
    'Guardar palavra-passe': 'Guardar contraseña',
    'ATIVO': 'ACTIVO',
    'INATIVO': 'INACTIVO',
    'FALHA': 'FALLO',
    'DESCONHECIDO': 'DESCONOCIDO',
    'PRONTO': 'LISTO',
    'LIGADO': 'CONECTADO',
    'EM BAIXO': 'CAÍDO',
    'EM ESPERA': 'EN ESPERA'
  }
};

function selectedLanguage() {
  return localStorage.getItem('svxlinkCtLanguage') || 'pt';
}

function translateTextNode(node, dict) {
  const text = node.nodeValue;
  const trimmed = text.trim();
  if (!trimmed || !dict[trimmed]) return;
  node.nodeValue = text.replace(trimmed, dict[trimmed]);
}

function translatePlaceholders(dict) {
  document.querySelectorAll('[placeholder]').forEach((el) => {
    const value = el.getAttribute('placeholder') || '';
    if (dict[value]) el.setAttribute('placeholder', dict[value]);
  });
}

function translatePage() {
  const lang = selectedLanguage();
  const dict = SVX_TRANSLATIONS[lang] || {};
  document.documentElement.lang = lang === 'pt' ? 'pt-PT' : lang;
  const select = document.getElementById('language-select');
  if (select) select.value = lang;
  if (lang === 'pt') return;
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
    acceptNode(node) {
      const parent = node.parentElement;
      if (!parent || ['SCRIPT', 'STYLE', 'CODE', 'PRE'].includes(parent.tagName)) {
        return NodeFilter.FILTER_REJECT;
      }
      return NodeFilter.FILTER_ACCEPT;
    }
  });
  const nodes = [];
  while (walker.nextNode()) nodes.push(walker.currentNode);
  nodes.forEach((node) => translateTextNode(node, dict));
  translatePlaceholders(dict);
}

document.addEventListener('DOMContentLoaded', () => {
  const select = document.getElementById('language-select');
  if (select) {
    select.value = selectedLanguage();
    select.addEventListener('change', () => {
      localStorage.setItem('svxlinkCtLanguage', select.value);
      window.location.reload();
    });
  }
  translatePage();
});

window.SVX_I18N = { translatePage };
