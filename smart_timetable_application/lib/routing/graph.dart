import 'dart:convert';
import 'package:flutter/services.dart' show rootBundle;

class MapNode {
  final String id;
  final double x; // normalized [0..1]
  final double y; // normalized [0..1]
  final String label;
  MapNode({required this.id, required this.x, required this.y, required this.label});
}

class MapEdge {
  final String from;
  final String to;
  final double w;
  MapEdge({required this.from, required this.to, required this.w});
}

class VenueRef {
  final String id;
  final String name;
  final String nodeId;
  VenueRef({required this.id, required this.name, required this.nodeId});
}

class FloorData {
  final String id;
  final String name;
  final String imageAsset;
  final Map<String, MapNode> nodes;
  final List<MapEdge> edges;
  final Map<String, VenueRef> venues;
  FloorData({
    required this.id,
    required this.name,
    required this.imageAsset,
    required this.nodes,
    required this.edges,
    required this.venues,
  });
}

class BuildingData {
  final String id;
  final String name;
  final Map<String, FloorData> floors;
  BuildingData({required this.id, required this.name, required this.floors});
}

class CampusRoutes {
  final Map<String, BuildingData> buildings;
  CampusRoutes({required this.buildings});

  static Future<CampusRoutes> loadFromAsset(String assetPath) async {
    final raw = await rootBundle.loadString(assetPath);
    final jsonObj = jsonDecode(raw);
    final Map<String, BuildingData> buildingMap = {};
    for (final b in jsonObj['buildings']) {
      final Map<String, FloorData> floorMap = {};
      for (final f in b['floors']) {
        final Map<String, MapNode> nodeMap = {};
        for (final n in f['nodes']) {
          nodeMap[n['id']] = MapNode(
            id: n['id'],
            x: (n['x'] as num).toDouble(),
            y: (n['y'] as num).toDouble(),
            label: (n['label'] ?? '') as String,
          );
        }
        final List<MapEdge> edges = [];
        for (final e in f['edges']) {
          edges.add(MapEdge(
            from: e['from'],
            to: e['to'],
            w: (e['w'] as num).toDouble(),
          ));
        }
        final Map<String, VenueRef> venueMap = {};
        for (final v in f['venues']) {
          venueMap[v['id']] = VenueRef(
            id: v['id'],
            name: v['name'],
            nodeId: v['nodeId'],
          );
        }
        floorMap[f['id']] = FloorData(
          id: f['id'],
          name: f['name'],
          imageAsset: (f['image'] ?? '') as String,
          nodes: nodeMap,
          edges: edges,
          venues: venueMap,
        );
      }
      buildingMap[b['id']] = BuildingData(id: b['id'], name: b['name'], floors: floorMap);
    }
    return CampusRoutes(buildings: buildingMap);
  }
}

// Dijkstra shortest path
List<String> shortestPath({
  required String startNodeId,
  required String goalNodeId,
  required Map<String, MapNode> nodes,
  required List<MapEdge> edges,
}) {
  final adjacency = <String, List<MapEdge>>{};
  for (final e in edges) {
    adjacency.putIfAbsent(e.from, () => []).add(e);
    // undirected
    adjacency.putIfAbsent(e.to, () => []).add(MapEdge(from: e.to, to: e.from, w: e.w));
  }

  final distances = <String, double>{ for (final id in nodes.keys) id: double.infinity };
  final previous = <String, String?>{ for (final id in nodes.keys) id: null };
  distances[startNodeId] = 0.0;

  // Simple Dijkstra without PriorityQueue (sufficient for small graphs)
  final unvisited = Set<String>.from(nodes.keys);
  while (unvisited.isNotEmpty) {
    // Pick unvisited node with smallest distance
    String u = unvisited.first;
    double best = distances[u] ?? double.infinity;
    for (final id in unvisited) {
      final d = distances[id] ?? double.infinity;
      if (d < best) {
        best = d;
        u = id;
      }
    }

    if (u == goalNodeId || (distances[u] ?? double.infinity) == double.infinity) {
      // Reached goal or remaining nodes are unreachable
      break;
    }

    unvisited.remove(u);
    for (final e in adjacency[u] ?? const []) {
      final alt = (distances[u] ?? double.infinity) + e.w;
      if (alt < (distances[e.to] ?? double.infinity)) {
        distances[e.to] = alt;
        previous[e.to] = u;
      }
    }
  }

  if (distances[goalNodeId] == null || distances[goalNodeId] == double.infinity) return [];
  final path = <String>[];
  String? cur = goalNodeId;
  while (cur != null) {
    path.add(cur);
    cur = previous[cur];
  }
  return path.reversed.toList();
}

