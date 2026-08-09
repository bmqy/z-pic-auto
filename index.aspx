<%@ Page Language="C#" %>
<script runat="server">
protected void Page_Load(object sender, System.EventArgs e) {
    Response.Redirect("index.php", false);
    Context.ApplicationInstance.CompleteRequest();
}
</script>
