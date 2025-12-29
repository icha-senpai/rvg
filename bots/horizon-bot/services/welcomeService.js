const {
  ActionRowBuilder,
  ButtonBuilder,
  ButtonStyle,
} = require('discord.js');

module.exports = {
  async sendWelcome(member) {
    const channelId = '1454412966907613227';
    const channel = member.guild.channels.cache.get(channelId);
    if (!channel) return;

    const content = `<@${member.id}>

**Welcome to the HORIZON Interstellar Discord!**

You’ve reached the **Arrival Platform**, your journey begins here! To continue beyond this point and unlock full server access, account and membership verification is required. Select the **Verify** button and complete the process through our official members hub.

If verification does not complete successfully, or if you are joining us in a **Diplomatic capacity** or have any kind of question, please click **Need Help?** and create a support ticket. One of our officers will get to you as soon as possible.
`;

    const buttons = new ActionRowBuilder().addComponents(
      new ButtonBuilder()
        .setLabel('Verify')
        .setStyle(ButtonStyle.Link)
        .setURL('https://horizoninterstellar.com/'),

      new ButtonBuilder()
        .setLabel('Need Help?')
        .setStyle(ButtonStyle.Link)
        .setURL(
          'https://discord.com/channels/113412259320954880/1454412972842811473'
        )
    );

    await channel.send({
      content,
      components: [buttons],
      allowedMentions: { users: [member.id] }, // 🔒 prevents accidental mass pings
    });
  },
};
