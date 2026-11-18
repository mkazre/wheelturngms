import { Directory, File, Paths } from 'expo-file-system';
import * as Sharing from 'expo-sharing';
import * as WebBrowser from 'expo-web-browser';
import { Platform } from 'react-native';

/**
 * Download a public PDF (invoice, quote, job card) and open the share sheet so it can go
 * straight to WhatsApp or email as a file. Falls back to opening it in the browser.
 */
export async function sharePdf(url: string, filename: string): Promise<void> {
  if (Platform.OS === 'web' || !(await Sharing.isAvailableAsync())) {
    await WebBrowser.openBrowserAsync(url);
    return;
  }
  const dir = new Directory(Paths.cache, 'pdf');
  if (!dir.exists) dir.create({ intermediates: true });
  const target = new File(dir, filename.replace(/[^A-Za-z0-9._-]/g, '_') + '.pdf');
  if (target.exists) target.delete();
  const file = await File.downloadFileAsync(url, target);
  await Sharing.shareAsync(file.uri, { mimeType: 'application/pdf', UTI: 'com.adobe.pdf', dialogTitle: filename });
}
