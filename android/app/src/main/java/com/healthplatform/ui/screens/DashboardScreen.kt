package com.healthplatform.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.healthplatform.viewmodels.DashboardViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun DashboardScreen(
    onNavigateToSubmit:      () -> Unit,
    onNavigateToSubmissions: () -> Unit,
    onNavigateToForms:       () -> Unit,
    viewModel: DashboardViewModel = hiltViewModel()
) {
    val summary by viewModel.summary.collectAsState()
    val isLoading by viewModel.isLoading.collectAsState()
    val pendingCount by viewModel.pendingOfflineCount.collectAsState()

    LaunchedEffect(Unit) { viewModel.loadSummary() }

    Scaffold(
        topBar = {
            TopAppBar(
                title = {
                    Column {
                        Text("Dashboard", fontWeight = FontWeight.Bold, fontSize = 20.sp)
                        Text("Health Platform", fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurface.copy(0.6f))
                    }
                },
                actions = {
                    if (pendingCount > 0) {
                        Badge {
                            Text("$pendingCount")
                        }
                        Spacer(Modifier.width(8.dp))
                    }
                    IconButton(onClick = { viewModel.loadSummary() }) {
                        Icon(Icons.Filled.Refresh, "Refresh")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = Color(0xFF1A5276),
                    titleContentColor = Color.White,
                    actionIconContentColor = Color.White
                )
            )
        },
        floatingActionButton = {
            ExtendedFloatingActionButton(
                onClick = onNavigateToSubmit,
                icon    = { Icon(Icons.Filled.Add, "New submission") },
                text    = { Text("Submit Data") },
                containerColor = Color(0xFF27AE60),
                contentColor   = Color.White
            )
        }
    ) { padding ->
        if (isLoading && summary == null) {
            Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator()
            }
        } else {
            LazyColumn(
                contentPadding = PaddingValues(
                    top    = padding.calculateTopPadding() + 16.dp,
                    bottom = padding.calculateBottomPadding() + 80.dp,
                    start  = 16.dp,
                    end    = 16.dp
                ),
                verticalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                // Offline banner
                if (pendingCount > 0) {
                    item {
                        Card(colors = CardDefaults.cardColors(containerColor = Color(0xFFFFF3CD))) {
                            Row(
                                modifier = Modifier.fillMaxWidth().padding(12.dp),
                                verticalAlignment = Alignment.CenterVertically,
                                horizontalArrangement = Arrangement.spacedBy(8.dp)
                            ) {
                                Icon(Icons.Filled.CloudOff, null, tint = Color(0xFF856404))
                                Text(
                                    "$pendingCount submission(s) pending sync",
                                    fontSize = 14.sp,
                                    color    = Color(0xFF856404)
                                )
                            }
                        }
                    }
                }

                // KPI Cards
                item {
                    Text("Overview", fontWeight = FontWeight.Bold, fontSize = 18.sp)
                }

                item {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        KpiCard(
                            modifier    = Modifier.weight(1f),
                            label       = "Total",
                            value       = summary?.get("total_submissions")?.toString() ?: "—",
                            icon        = Icons.Filled.Assignment,
                            color       = Color(0xFF1A5276)
                        )
                        KpiCard(
                            modifier  = Modifier.weight(1f),
                            label     = "This Month",
                            value     = summary?.get("month_submissions")?.toString() ?: "—",
                            icon      = Icons.Filled.CalendarMonth,
                            color     = Color(0xFF2980B9)
                        )
                    }
                }

                item {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        KpiCard(
                            modifier = Modifier.weight(1f),
                            label    = "Pending Review",
                            value    = summary?.get("pending_review")?.toString() ?: "—",
                            icon     = Icons.Filled.Pending,
                            color    = Color(0xFFE67E22)
                        )
                        KpiCard(
                            modifier = Modifier.weight(1f),
                            label    = "Reporting Rate",
                            value    = (summary?.get("reporting_rate")?.toString() ?: "—") + "%",
                            icon     = Icons.Filled.TrendingUp,
                            color    = Color(0xFF27AE60)
                        )
                    }
                }

                // Quick actions
                item {
                    Text("Quick Actions", fontWeight = FontWeight.Bold, fontSize = 18.sp, modifier = Modifier.padding(top = 8.dp))
                }

                item {
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        QuickActionCard(
                            modifier = Modifier.weight(1f),
                            label    = "Submit Data",
                            icon     = Icons.Filled.Edit,
                            color    = Color(0xFF27AE60),
                            onClick  = onNavigateToSubmit
                        )
                        QuickActionCard(
                            modifier = Modifier.weight(1f),
                            label    = "My Submissions",
                            icon     = Icons.Filled.List,
                            color    = Color(0xFF1A5276),
                            onClick  = onNavigateToSubmissions
                        )
                        QuickActionCard(
                            modifier = Modifier.weight(1f),
                            label    = "Browse Forms",
                            icon     = Icons.Filled.Description,
                            color    = Color(0xFF8E44AD),
                            onClick  = onNavigateToForms
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun KpiCard(
    modifier: Modifier,
    label: String,
    value: String,
    icon: ImageVector,
    color: Color
) {
    Card(
        modifier  = modifier,
        elevation = CardDefaults.cardElevation(2.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Icon(icon, null, tint = color, modifier = Modifier.size(28.dp))
            Text(value, fontWeight = FontWeight.Bold, fontSize = 24.sp, color = color)
            Text(label, fontSize = 12.sp, color = MaterialTheme.colorScheme.onSurface.copy(0.6f))
        }
    }
}

@Composable
private fun QuickActionCard(
    modifier: Modifier,
    label: String,
    icon: ImageVector,
    color: Color,
    onClick: () -> Unit
) {
    Card(
        modifier  = modifier.clickable { onClick() },
        colors    = CardDefaults.cardColors(containerColor = color.copy(alpha = 0.1f)),
        elevation = CardDefaults.cardElevation(0.dp)
    ) {
        Column(
            modifier            = Modifier.fillMaxWidth().padding(12.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Icon(icon, null, tint = color, modifier = Modifier.size(28.dp))
            Text(label, fontSize = 11.sp, fontWeight = FontWeight.Medium, color = color)
        }
    }
}
