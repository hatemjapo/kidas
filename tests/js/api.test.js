import axios from 'axios';
import { vi } from 'vitest';
import api, { csrf } from '../../resources/js/lib/api';

test('talks to the versioned api', () => {
    expect(api.defaults.baseURL).toBe('/api/v1');
});

test('sends the session cookie and the csrf header', () => {
    expect(api.defaults.withCredentials).toBe(true);
    expect(api.defaults.withXSRFToken).toBe(true);
});

test('asks for json', () => {
    expect(api.defaults.headers.Accept).toBe('application/json');
    expect(api.defaults.headers['X-Requested-With']).toBe('XMLHttpRequest');
});

test('fetches the csrf cookie with credentials', async () => {
    const get = vi.spyOn(axios, 'get').mockResolvedValue({ status: 204 });

    await csrf();

    expect(get).toHaveBeenCalledWith('/sanctum/csrf-cookie', { withCredentials: true });

    get.mockRestore();
});
