export interface MailContent {
  subject: string;
  text: string;
  html: string;
}

export interface MailMessage extends MailContent {
  to: string;
}
