import remoteform from 'remoteform';

remoteform('*[data-post-notifications]', {
  request: {
    headers: {
      'X-Remoteform': 'post-notifications'
    }
  }
});
